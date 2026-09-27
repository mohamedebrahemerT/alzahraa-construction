<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc></url><url><loc>{{ route('en.home') }}</loc></url>
    @foreach($pages->where('slug', '!=', 'home') as $page)<url><loc>{{ url('/'.$page->slug) }}</loc><lastmod>{{ $page->updated_at->toAtomString() }}</lastmod></url><url><loc>{{ url('/en/'.$page->slug) }}</loc><lastmod>{{ $page->updated_at->toAtomString() }}</lastmod></url>@endforeach
    @foreach($items as $item)@php $path = '/'.($item->kind === 'project' ? 'projects/' : 'services/').$item->slug; @endphp<url><loc>{{ url($path) }}</loc><lastmod>{{ $item->updated_at->toAtomString() }}</lastmod></url><url><loc>{{ url('/en'.$path) }}</loc><lastmod>{{ $item->updated_at->toAtomString() }}</lastmod></url>@endforeach
</urlset>
