{{-- Modal compartido para rechazar un pedido sin validar. Uso: rechazarPedido(urlDelFormulario, codigo) --}}
<x-modal id="modal-rechazar" titulo="Rechazar pedido">
    <form method="POST" id="form-rechazar" class="space-y-4">
        @csrf
        <p class="text-sm muted">El pedido <b id="rechazar-codigo" class="text-neutral-900"></b> no entrará a la cola. La persona verá el motivo en su enlace de seguimiento.</p>
        <div>
            <label class="label" for="rechazar-motivo">Motivo</label>
            <textarea id="rechazar-motivo" name="motivo" rows="2" class="input" required maxlength="255" placeholder="No pertenece a la UNIQ / pedido duplicado / datos falsos"></textarea>
        </div>
        <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
            <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
            <button class="btn btn-danger"><i class="ti ti-x"></i>Rechazar</button>
        </div>
    </form>
</x-modal>
<script>
    window.rechazarPedido = (accion, codigo) => {
        document.getElementById('form-rechazar').action = accion;
        document.getElementById('rechazar-codigo').textContent = codigo;
        document.getElementById('rechazar-motivo').value = '';
        document.getElementById('modal-rechazar').showModal();
    };
</script>
