{{--
    Parcial: campos del formulario de usuario.
    Lo comparten las vistas de creación y edición de usuarios. Si recibe $user (modo edición)
    los campos se rellenan con sus datos; old() conserva lo escrito si falla la validación.
    Necesita del controlador $roles, la lista de roles de spatie/laravel-permission.
--}}
{{-- Si no se pasó un usuario, lo dejamos en null: así sabemos que estamos creando uno nuevo. --}}
@php($user = $user ?? null)
{{-- Datos personales: nombre completo y correo electrónico (el correo sirve para iniciar sesión). --}}
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
{{-- Datos de contacto opcionales: documento, teléfono y dirección. --}}
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
{{--
    Rol del usuario. Cada usuario tiene un solo rol, y de él dependen los permisos y el menú
    que verá. En edición se preselecciona el rol que ya tiene asignado.
--}}
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
{{--
    Contraseña y confirmación. Al crear un usuario son obligatorias; al editar son opcionales
    y, si se dejan vacías, se mantiene la contraseña actual.
--}}
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
{{-- Casilla de usuario activo; en una cuenta nueva viene marcada por defecto. --}}
<label class="inline-flex items-center gap-2">
    <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm" @checked(old('activo', $user?->activo ?? true))>
    <span class="text-sm text-slate-700">Usuario activo</span>
</label>
