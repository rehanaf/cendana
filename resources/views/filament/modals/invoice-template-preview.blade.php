<div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
    <iframe
        src="{{ route('invoice.template.preview', $record) }}"
        style="width:100%;height:70vh;min-height:480px;border:0;display:block;background:#fff;"
        title="{{ $record->name }}"
    ></iframe>
</div>