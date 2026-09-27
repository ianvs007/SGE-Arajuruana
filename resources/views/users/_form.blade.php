@php($user = $user ?? null)
<div>
    <x-input-label for="name" value="Nombre completo" />
    <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $user?->name)" required />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>
<div>
    <x-input-label for="email" value="Correo electrónico" />
    <x-text-input id="email" type="email" name="email" class="block mt-1 w-full" :value="old('email', $user?->email)" required />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="documento" value="Documento" />
        <x-text-input id="documento" name="documento" class="block mt-1 w-full" :value="old('documento', $user?->documento)" />
        <x-input-error :messages="$errors->get('documento')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="telefono" value="Teléfono" />
        <x-text-input id="telefono" name="telefono" class="block mt-1 w-full" :value="old('telefono', $user?->telefono)" />
        <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
    </div>
</div>
<div>
    <x-input-label for="direccion" value="Dirección" />
    <x-text-input id="direccion" name="direccion" class="block mt-1 w-full" :value="old('direccion', $user?->direccion)" />
    <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
</div>
<div>
    <x-input-label for="role" value="Rol" />
    <select id="role" name="role" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
        <option value="">Seleccione un rol</option>
        @foreach ($roles as $role)
            <option value="{{ $role->name }}" @selected(old('role', $user?->roles->first()?->name) === $role->name)>{{ $role->name }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('role')" class="mt-2" />
</div>
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="password" value="{{ $user ? 'Nueva contraseña (opcional)' : 'Contraseña' }}" />
        <x-text-input id="password" type="password" name="password" class="block mt-1 w-full" :required="!$user" />
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="password_confirmation" value="Confirmar contraseña" />
        <x-text-input id="password_confirmation" type="password" name="password_confirmation" class="block mt-1 w-full" :required="!$user" />
    </div>
</div>
<label class="inline-flex items-center gap-2">
    <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm" @checked(old('activo', $user?->activo ?? true))>
    <span class="text-sm text-slate-700">Usuario activo</span>
</label>
