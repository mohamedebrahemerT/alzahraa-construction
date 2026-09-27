@extends('admin.layout')
@section('title','سجل التغييرات')
@section('content')
<div class="admin-title"><div><h1>سجل التغييرات</h1><p class="text-secondary mb-0">العمليات الأساسية على الصفحات والإعدادات والوسائط والطلبات والحسابات.</p></div></div>
<section class="admin-panel"><div class="table-wrap"><table class="admin-table"><thead><tr><th>العملية</th><th>النوع</th><th>المنفذ</th><th>التاريخ</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->summary }}</td><td>{{ $log->action }}</td><td>{{ $log->user?->name ?? 'النظام' }}</td><td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td></tr>@empty<tr><td colspan="4">لا توجد تغييرات.</td></tr>@endforelse</tbody></table></div></section><div class="pager">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
