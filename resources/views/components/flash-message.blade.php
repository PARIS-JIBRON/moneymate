@foreach (['success','error','warning','info'] as $type)
    @php
        $bgColor = match($type) {
            'success' => 'bg-green-600 text-white',
            'error' => 'bg-red-600 text-white',
            'warning' => 'bg-yellow-600 text-white',
            'info' => 'bg-blue-600 text-white',
        };
    @endphp

    @if (session()->has($type))
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 3000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform -translate-y-4"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-4"
            class="{{ $bgColor }} fixed top-4 right-4 z-50 p-4 mb-4 text-sm rounded-lg" role="alert">
            <span class="font-medium">{{ ucfirst($type) }}!</span> {{ session($type) }}
        </div>
    @endif
@endforeach
