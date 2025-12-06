<div>
  <p>Category: {{ $category }}</p>
  <p>{{ $message }}</p>
  @if (!empty($data))
    <pre>{{ json_encode($data) }}</pre>
  @endif
</div>
