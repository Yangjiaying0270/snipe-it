@extends('layouts/default')

{{-- Page title --}}
@section('title')
    延期申请 - {{ $asset->name }} @parent
@stop

@section('header_right')
    <a href="{{ route('view-assets') }}" class="btn btn-primary">
        <x-icon type="arrow-left" /> 返回我的资产
    </a>
@stop

{{-- Page content --}}
@section('content')
    <x-container columns="1">
        <x-box>
            <x-slot:table_header>
                <h3 class="box-title">
                    <x-icon type="due" class="text-orange fa-fw" />
                    申请归还日延期
                </h3>
            </x-slot:table_header>

            <form method="POST" action="{{ route('account.requests.extend.store', $asset) }}" accept-charset="utf-8">
                {{ csrf_field() }}

                {{-- 资产信息回显（只读） --}}
                <div class="form-group">
                    <label class="col-md-3 control-label">资产</label>
                    <div class="col-md-9">
                        <p class="form-control-static">
                            <strong>{{ $asset->name }}</strong>
                            @if ($asset->asset_tag)
                                <span class="text-muted">({{ $asset->asset_tag }})</span>
                            @endif
                            @if ($asset->model)
                                <span class="text-muted">— {{ $asset->model->name }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-3 control-label">当前预计归还日</label>
                    <div class="col-md-9">
                        <p class="form-control-static">
                            @if ($asset->expected_checkin)
                                {{ \Carbon\Carbon::parse($asset->expected_checkin)->format('Y-m-d') }}
                            @else
                                <span class="text-muted">未设置</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- 延期日期（必须晚于原归还日） --}}
                <div class="form-group {{ $errors->has('expected_return_date') ? 'has-error' : '' }}">
                    <label for="extend_date" class="col-md-3 control-label required">
                        延期后归还日 <span class="text-red">*</span>
                    </label>
                    <div class="col-md-9">
                        <div class="input-group date" data-provide="datepicker"
                             data-date-format="yyyy-mm-dd"
                             data-date-start-date="{{ $asset->expected_checkin ? \Carbon\Carbon::parse($asset->expected_checkin)->addDay()->format('Y-m-d') : now()->addDay()->format('Y-m-d') }}"
                             data-date-autoclose="true"
                             data-date-today-highlight="true">
                            <input type="text"
                                   name="expected_return_date"
                                   id="extend_date"
                                   class="form-control"
                                   value="{{ old('expected_return_date') }}"
                                   required
                                   placeholder="选择延期后的归还日期">
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        </div>
                        @if ($errors->has('expected_return_date'))
                            <span class="help-block text-red">{{ $errors->first('expected_return_date') }}</span>
                        @endif
                        <span class="help-block">延期日期必须晚于原归还日
                            @if ($asset->expected_checkin)
                                (原归还日：{{ \Carbon\Carbon::parse($asset->expected_checkin)->format('Y-m-d') }})
                            @endif
                        </span>
                    </div>
                </div>

                {{-- 审批人 --}}
                <div class="form-group {{ $errors->has('approver_id') ? 'has-error' : '' }}">
                    <label for="approver_id" class="col-md-3 control-label required">
                        审批人 <span class="text-red">*</span>
                    </label>
                    <div class="col-md-9">
                        <select name="approver_id" id="approver_id" class="form-control js-data-ajax"
                                data-select2-id="approver_id" required>
                            <option value="">选择审批人...</option>
                            @foreach ($approvers as $user)
                                <option value="{{ $user->id }}"
                                    {{ old('approver_id', $defaultApproverId) == $user->id ? 'selected' : '' }}>
                                    {{ $user->full_name }}
                                    @if ($user->email)
                                        ({{ $user->email }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('approver_id'))
                            <span class="help-block text-red">{{ $errors->first('approver_id') }}</span>
                        @endif
                    </div>
                </div>

                {{-- 备注 --}}
                <div class="form-group {{ $errors->has('notes') ? 'has-error' : '' }}">
                    <label for="notes" class="col-md-3 control-label">备注</label>
                    <div class="col-md-9">
                        <textarea name="notes" id="notes" rows="3" class="form-control"
                                  placeholder="简要说明延期原因（可选）">{{ old('notes') }}</textarea>
                        @if ($errors->has('notes'))
                            <span class="help-block text-red">{{ $errors->first('notes') }}</span>
                        @endif
                    </div>
                </div>

                {{-- 提交按钮 --}}
                <div class="form-group">
                    <div class="col-md-offset-3 col-md-9">
                        <button type="submit" class="btn btn-primary">
                            <x-icon type="checkmark" class="fa-fw" /> 提交延期申请
                        </button>
                        <a href="{{ route('view-assets') }}" class="btn btn-default">
                            取消
                        </a>
                    </div>
                </div>
            </form>
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    <script nonce="{{ csrf_token() }}">
        $(function () {
            // 初始化日期选择器
            $('.input-group.date').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                startDate: "{{ $asset->expected_checkin ? \Carbon\Carbon::parse($asset->expected_checkin)->addDay()->format('Y-m-d') : now()->addDay()->format('Y-m-d') }}",
                language: 'zh-CN'
            });
        });
    </script>
@stop
