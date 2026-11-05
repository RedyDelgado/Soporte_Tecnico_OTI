import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Chart = Chart;

const csrf = () => document.querySelector('meta[name=csrf-token]').content;

async function postJson(url, body) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    const j = await r.json();
    if (!r.ok) throw new Error(Object.values(j.errors ?? {})[0]?.[0] ?? j.message ?? 'No se pudo guardar');
    return j;
}

/* ---------- Notificaciones (arriba a la derecha, se van solas) ---------- */

let idToast = 0;
Alpine.store('toasts', {
    lista: [],
    push(texto, tipo = 'ok', ms = 4000) {
        const id = ++idToast;
        this.lista.push({ id, texto, tipo, saliendo: false });
        setTimeout(() => this.quitar(id), ms);
    },
    // Sale por el mismo borde por el que entró y luego se retira de la lista.
    quitar(id) {
        const toast = this.lista.find((t) => t.id === id);
        if (!toast || toast.saliendo) return;
        toast.saliendo = true;
        setTimeout(() => (this.lista = this.lista.filter((t) => t.id !== id)), 150);
    },
});
window.toast = (texto, tipo, ms) => Alpine.store('toasts').push(texto, tipo, ms);

/* ---------- Loading ---------- */

function cargando(boton) {
    document.body.classList.add('cargando');
    if (!boton) return;
    setTimeout(() => {
        boton.disabled = true;
        const icono = boton.querySelector('i.ti');
        const spin = Object.assign(document.createElement('span'), { className: 'spinner' });
        icono ? icono.replaceWith(spin) : boton.prepend(spin);
    });
}

window.addEventListener('beforeunload', () => document.body.classList.add('cargando'));
window.addEventListener('pageshow', () => document.body.classList.remove('cargando'));

/* ---------- Confirmaciones ---------- */

// <form data-confirmar="¿Seguro?" data-confirmar-boton="Sí, cerrar"> pide confirmación en un modal antes de enviar.
window.confirmar = (mensaje, textoBoton = 'Confirmar', peligro = false) => new Promise((resolve) => {
    const d = document.getElementById('modal-confirmar');
    d.querySelector('[data-mensaje]').textContent = mensaje;
    const ok = d.querySelector('[data-ok]');
    ok.textContent = textoBoton;
    ok.className = 'btn ' + (peligro ? 'btn-danger' : 'btn-primary');
    d.onclose = () => resolve(d.returnValue === 'ok');
    d.returnValue = '';
    d.showModal();
    ok.focus();
});

document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.method === 'dialog') return;
    if (form.dataset.confirmar && !form.dataset.confirmado) {
        e.preventDefault();
        const texto = typeof form.dataset.confirmar === 'string' ? form.dataset.confirmar : '';
        const mensaje = texto.replace('{opcion}', form.querySelector('select')?.selectedOptions[0]?.text.split(' · ')[0] ?? '');
        if (await confirmar(mensaje, form.dataset.confirmarBoton, 'peligro' in form.dataset)) {
            form.dataset.confirmado = '1';
            form.requestSubmit(e.submitter);
        } else if ('resetear' in form.dataset) {
            form.reset();
        }
        return;
    }
    cargando(e.submitter ?? form.querySelector('button:not([type=button])'));
});

/* ---------- Bandeja en vivo ---------- */

function beep() {
    try {
        const ctx = new AudioContext();
        [880, 1175].forEach((f, i) => {
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = f;
            g.gain.setValueAtTime(0.15, ctx.currentTime + i * 0.15);
            g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.15 + 0.3);
            o.connect(g).connect(ctx.destination);
            o.start(ctx.currentTime + i * 0.15);
            o.stop(ctx.currentTime + i * 0.15 + 0.3);
        });
    } catch { /* sin audio, no pasa nada */ }
}

// Pide el mismo listado en modo parcial cada 10 s.
Alpine.data('bandeja', (maxId) => ({
    maxId,
    pausa: false,
    nuevos: 0,
    actualizando: false,
    init() {
        setInterval(() => this.refrescar(), 10000);
    },
    async refrescar() {
        if (this.pausa || document.hidden || document.querySelector('dialog[open]')) return;
        this.actualizando = true;
        try {
            const url = new URL(location.href);
            url.searchParams.set('parcial', 1);
            const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) return;
            this.$refs.contenido.innerHTML = await r.text();
            const max = Number(this.$refs.contenido.querySelector('[data-max]')?.dataset.max ?? 0);
            if (max > this.maxId) {
                // Solo las filas recién llegadas entran con animación, no todo el listado en cada consulta.
                this.$refs.contenido.querySelectorAll('tr[data-id]').forEach((tr) => {
                    if (Number(tr.dataset.id) > this.maxId) tr.classList.add('entra');
                });
                this.nuevos = max - this.maxId;
                toast(this.nuevos === 1 ? 'Llegó 1 pedido nuevo' : `Llegaron ${this.nuevos} pedidos nuevos`, 'info', 6000);
                beep();
                setTimeout(() => (this.nuevos = 0), 6000);
            }
            this.maxId = max;
        } finally {
            this.actualizando = false;
        }
    },
}));

/* ---------- Avisos del sistema operativo (Web Push) ---------- */

function llave(b64) {
    const pad = '='.repeat((4 - (b64.length % 4)) % 4);
    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

Alpine.data('avisos', (vapid) => ({
    soportado: 'serviceWorker' in navigator && 'PushManager' in window,
    activo: false,
    async init() {
        if (!this.soportado) return;
        this.activo = Notification.permission === 'granted';
        if (this.activo) this.suscribir();
    },
    async activar() {
        if ((await Notification.requestPermission()) !== 'granted') return;
        this.activo = true;
        this.suscribir();
    },
    async suscribir() {
        const reg = await navigator.serviceWorker.register('/sw.js');
        const sub = (await reg.pushManager.getSubscription())
            ?? (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: llave(vapid) }));
        await postJson('/push', sub.toJSON());
    },
}));

/* ---------- Fotos: hasta 3, reducidas a 850 px de ancho ---------- */

const ANCHO_MAX = 850;

async function comprimir(archivo) {
    const img = await createImageBitmap(archivo);
    const escala = Math.min(1, ANCHO_MAX / img.width);
    const canvas = Object.assign(document.createElement('canvas'), {
        width: Math.round(img.width * escala),
        height: Math.round(img.height * escala),
    });
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
    // WebP pesa menos; si el navegador no sabe generarlo, JPEG.
    let blob = await new Promise((r) => canvas.toBlob(r, 'image/webp', 0.78));
    if (!blob || blob.type !== 'image/webp') blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.8));
    const ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
    return new File([blob], archivo.name.replace(/\.\w+$/, '') + '.' + ext, { type: blob.type });
}

const kb = (b) => (b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB');

Alpine.data('fotos', () => ({
    lista: [],
    procesando: false,
    async agregar(archivos) {
        this.procesando = true;
        try {
            for (const a of [...archivos].slice(0, 3 - this.lista.length)) {
                if (!a.type.startsWith('image/')) continue;
                const f = await comprimir(a);
                this.lista.push({ archivo: f, url: URL.createObjectURL(f), peso: kb(f.size) });
            }
        } finally {
            this.procesando = false;
            this.sincronizar();
        }
    },
    quitar(i) {
        URL.revokeObjectURL(this.lista[i].url);
        this.lista.splice(i, 1);
        this.sincronizar();
    },
    // Pone los archivos comprimidos en el <input> real que se envía con el formulario.
    sincronizar() {
        const dt = new DataTransfer();
        this.lista.forEach((f) => dt.items.add(f.archivo));
        this.$refs.input.files = dt.files;
    },
}));

/* ---------- Categoría nueva (modal) ---------- */

Alpine.data('categoriaNueva', () => ({
    nombre: '',
    especialidad: 'soporte',
    error: '',
    guardando: false,
    async guardar() {
        this.error = '';
        this.guardando = true;
        try {
            const c = await postJson('/categorias', { nombre: this.nombre, especialidad: this.especialidad });
            const sel = this.$refs.select;
            sel.add(new Option(c.nombre, c.id, true, true));
            sel.dispatchEvent(new Event('change'));
            this.$refs.modal.close();
            this.nombre = '';
        } catch (e) {
            this.error = e.message;
        } finally {
            this.guardando = false;
        }
    },
}));

/* ---------- Registro de pedido: persona + sede + área ---------- */

// Combobox de áreas: lee areas / area_id del x-data padre. El texto siempre vuelve al área elegida al cerrar.
const sinTildes = (t) => t.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
Alpine.data('comboArea', () => ({
    abierto: false,
    q: '',
    idx: 0,
    init() {
        this.$watch('area_id', () => this.cerrar());
        this.$watch('sede_id', () => this.cerrar());
        this.cerrar();
    },
    get filtradas() {
        const t = sinTildes(this.q.trim());
        return this.areas.filter((a) => sinTildes(a.nombre).includes(t));
    },
    abrir() {
        if (!this.abierto) {
            this.abierto = true;
            this.q = '';
        }
    },
    cerrar() {
        this.abierto = false;
        this.q = this.areas.find((a) => a.id == this.area_id)?.nombre ?? '';
    },
    elegir(a) {
        this.area_id = a.id;
        this.cerrar();
    },
}));

Alpine.data('registro', (sedes, inicial) => ({
    sedes,
    q: '',
    resultados: [],
    buscando: false,
    persona: inicial.persona,
    nombre: inicial.nombre,
    telefono: inicial.telefono,
    sede_id: inicial.sede_id,
    area_id: inicial.area_id,
    nuevaArea: '',
    errorArea: '',
    guardandoArea: false,
    temporizador: null,

    get areas() {
        return this.sedes.find((s) => s.id == this.sede_id)?.areas ?? [];
    },
    get areaNombre() {
        return this.areas.find((a) => a.id == this.area_id)?.nombre;
    },
    // La persona ya existe pero hoy está en otra área: se moverá y su historial queda en el área anterior.
    get rota() {
        return this.persona && this.area_id && this.area_id != this.persona.area_id;
    },
    buscar() {
        clearTimeout(this.temporizador);
        if (this.q.trim().length < 2) return (this.resultados = []);
        this.temporizador = setTimeout(async () => {
            this.buscando = true;
            try {
                const r = await fetch('/personas/buscar?q=' + encodeURIComponent(this.q.trim()));
                this.resultados = await r.json();
            } finally {
                this.buscando = false;
            }
        }, 300);
    },
    elegir(p) {
        Object.assign(this, { persona: p, nombre: p.nombre, telefono: p.telefono ?? '', sede_id: p.sede_id, q: '', resultados: [] });
        this.$nextTick(() => (this.area_id = p.area_id));
    },
    nueva() {
        Object.assign(this, { persona: null, nombre: this.q.trim(), telefono: '', q: '', resultados: [] });
        this.$nextTick(() => this.$refs.nombre.focus());
    },
    cambiarPersona() {
        Object.assign(this, { persona: null, nombre: '', telefono: '', sede_id: '', area_id: '' });
        this.$nextTick(() => this.$refs.buscador.focus());
    },
    async guardarArea() {
        this.errorArea = '';
        this.guardandoArea = true;
        try {
            const a = await postJson('/areas', { sede_id: this.sede_id, nombre: this.nuevaArea });
            this.sedes.find((s) => s.id == a.sede_id).areas.push({ id: a.id, nombre: a.nombre });
            this.$nextTick(() => (this.area_id = a.id));
            this.$refs.modalArea.close();
            this.nuevaArea = '';
        } catch (e) {
            this.errorArea = e.message;
        } finally {
            this.guardandoArea = false;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
