@props(['contentExpression' => "''", 'recordExpression' => 'null', 'keyExpression' => "'new'", 'name' => 'butir_snp', 'module' => 'snp', 'label' => 'Isi Butir SNP'])
<div data-snp-editor x-data="snpButirEditor(@js($module), @js($label))" x-effect="sync({{ $keyExpression }}, {{ $contentExpression }}, {{ $recordExpression }})" class="snp-editor">
    <input type="hidden" name="{{ $name }}" :value="value">
    <input type="hidden" name="editor_record_id" :value="{{ $recordExpression }}">
    <div x-ref="surface" class="snp-editor__surface" :aria-busy="busy">
        <div class="snp-editor__toolbar" role="toolbar" aria-label="Format {{ $label }}" @mousedown.prevent>
            <select aria-label="Ukuran huruf" :value="fontSize()" @mousedown.stop @change="command('setFontSize', $event.target.value)" :disabled="busy">
                @foreach ([12, 14, 16, 18, 20, 24] as $size)
                    <option value="{{ $size }}px">{{ $size }} px</option>
                @endforeach
            </select>
            @foreach (['bold' => ['B', 'Tebal', 'toggleBold'], 'italic' => ['I', 'Miring', 'toggleItalic'], 'underline' => ['U', 'Garis bawah', 'toggleUnderline'], 'bulletList' => ['• Daftar', 'Daftar poin', 'toggleBulletList'], 'orderedList' => ['1. Daftar', 'Daftar nomor', 'toggleOrderedList']] as $type => [$buttonLabel, $title, $command])
                <button type="button" title="{{ $title }}" aria-label="{{ $title }}" :aria-pressed="active('{{ $type }}')" @click="command('{{ $command }}')" :disabled="busy">{{ $buttonLabel }}</button>
            @endforeach
            @foreach (['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan', 'justify' => 'Rata penuh'] as $align => $buttonLabel)
                <button type="button" :aria-pressed="active({ textAlign: '{{ $align }}' })" @click="command('setTextAlign', '{{ $align }}')" :disabled="busy">{{ $buttonLabel }}</button>
            @endforeach
            <button type="button" @click="chooseImage()" :disabled="busy">+ Gambar</button>
            <button type="button" title="Urungkan (Ctrl+Z)" @click="command('undo')" :disabled="busy">↶</button>
            <button type="button" title="Ulangi (Ctrl+Shift+Z)" @click="command('redo')" :disabled="busy">↷</button>
            <button type="button" @click="command('clear')" :disabled="busy">Hapus format</button>
            <button type="button" @click="enlarge()" x-text="expanded ? 'Selesai memperbesar' : 'Perbesar editor'"></button>
        </div>
        <div x-show="active('image')" class="snp-editor__image-toolbar">
            <span>Ukuran gambar:</span>
            @foreach ([25, 50, 75, 100] as $width)
                <button type="button" :aria-pressed="active('image', { width: {{ $width }} })" @mousedown.prevent @click="imageWidth({{ $width }})" :disabled="busy">{{ $width }}%</button>
            @endforeach
        </div>
        <div x-ref="canvas" class="snp-editor__canvas" x-ignore></div>
        <p class="snp-editor__help">JPG/PNG maksimal 2 MB per gambar. Klik gambar untuk mengatur ukurannya. Maksimal 20 gambar.</p>
        <p x-show="busy" class="snp-editor__help" role="status">Mengunggah gambar…</p>
        <p x-show="error" x-text="error" class="snp-editor__error" role="alert"></p>
        <input x-ref="imageInput" type="file" accept="image/jpeg,image/png" @change="upload($event.target.files[0])" class="hidden">
    </div>
    <dialog x-ref="dialog" class="snp-editor__dialog" aria-label="Perbesar editor {{ $label }}" @close="restore()" @click.stop @keydown.stop
        @keydown.escape.capture.stop.prevent="$refs.dialog.close()"></dialog>
</div>
