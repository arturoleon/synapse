@isset($input)
@if (!empty($image))
<message type="image_url">
@else
<message type="user">
@endif
{{$input}}
</message>
@endisset
