{{-- Botón secundario que EN REALIDAD es un enlace (HTML válido: no anida
     <button> dentro de <a>). Uso: <x-secondary-link-button :href="route('...')">Texto</x-secondary-link-button>
     Acepta target, rel y cualquier atributo HTML extra vía $attributes. --}}
<a {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</a>
