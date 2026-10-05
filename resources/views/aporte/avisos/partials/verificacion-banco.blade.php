{{--
    Parcial: verificación bancaria del operador.
    Campos obligatorios para validar un aviso por QR: la casilla con la que el
    operador declara que verificó el ingreso en la plataforma de su banco y el
    número de operación que figura allí. El número de operación no puede
    repetirse en otro pago vigente, lo que impide usar dos veces el mismo pago.
--}}
<div class="space-y-4">
    <label class="flex items-start gap-2 text-sm bg-sky-50 border border-sky-200 rounded p-3">
        <input type="checkbox" name="verificado_banco" value="1" class="mt-0.5 rounded border-gray-300 text-sky-600"
            @checked(old('verificado_banco')) required>
        <span>
            <strong>Verifiqué en la plataforma del banco que el dinero ingresó</strong> a la cuenta del colegio
            por el monto indicado.
        </span>
    </label>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="operacion_bancaria" value="Número de operación bancaria" />
            <x-text-input id="operacion_bancaria" name="operacion_bancaria" class="block mt-1 w-full font-mono" maxlength="60"
                :value="old('operacion_bancaria')" required placeholder="Tal como figura en el banco" />
            <p class="text-xs text-slate-500 mt-1">No puede repetirse: el sistema rechaza una operación ya usada en otro pago.</p>
        </div>
        <div>
            <x-input-label for="observacion" value="Observación del operador (opcional)" />
            <x-text-input id="observacion" name="observacion" class="block mt-1 w-full" maxlength="500" :value="old('observacion')" />
        </div>
    </div>
</div>
