## {{ $route['metadata']['title'] ?: $route['uri']}}
@component('scribe::components.badges.auth', ['authenticated' => $route['metadata']['authenticated']])
@endcomponent

{!! $route['metadata']['description'] ?: ''!!}

> Example request:

@foreach($settings['languages'] as $language)
@include("scribe::partials.example-requests.$language")

@endforeach

@if(in_array('GET',$route['methods']) || (isset($route['showresponse']) && $route['showresponse']))
@foreach($route['responses'] as $response)
> Example response ({{$response['description'] ?? $response['status']}}):

```json
@if(is_object($response['content']) || is_array($response['content']))
{!! json_encode($response['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@elseif(\Illuminate\Support\Str::startsWith($response['content'], "<<binary>>"))
Binary data - {{ str_replace("<<binary>>","",$response['content']) }}
@else
{!! json_encode(json_decode($response['content']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endforeach
@endif

### Request
@foreach($route['methods'] as $method)
@component('scribe::components.badges.http-method', ['method' => $method])@endcomponent **`{{$route['uri']}}`**

@endforeach
@if(count($route['urlParameters']))
<h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
@foreach($route['urlParameters'] as $attribute => $parameter)
@component('scribe::components.field-description', [
  'name' => $attribute,
  'type' => null,
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
])
@endcomponent
@endforeach
@endif
@if(count($route['queryParameters']))
<h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
@foreach($route['queryParameters'] as $attribute => $parameter)
@component('scribe::components.field-description', [
  'name' => $attribute,
  'type' => null,
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
])
@endcomponent
@endforeach
@endif
@if(count($route['bodyParameters']))
<h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
@foreach($route['bodyParameters'] as $attribute => $parameter)
@component('scribe::components.field-description', [
  'name' => $attribute,
  'type' => $parameter['type'] ?? null,
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
])
@endcomponent
@endforeach
@endif

@if(count($route['responseFields'] ?? []))
<h4 class="fancy-heading-panel"><b>Response Fields</b></h4>
@foreach($route['responseFields'] as $field)
@component('scribe::components.field-description', [
  'name' => $field['name'],
  'type' => $field['type'],
  'required' => true,
  'description' => $field['description'],
])
@endcomponent
@endforeach
@endif
