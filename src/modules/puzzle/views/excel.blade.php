<?php
ob_end_clean();
ob_start();
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header('Content-Disposition: attachment; filename=tailan-' . date('Ymd') . '.xls');
header('Pragma: no-cache');
header('Expires: 0');
?>

<meta charset="UTF-8">
<link rel="stylesheet" href="http://om.app/assets/report/excel.css">
<table id="report-table" border="1" cellspacing="0">
    <thead>
    @for($r = 0; $r < count($header); $r++)
        <tr>
            @for($d = 0; $d < count($header[$r]); $d++)
                @if(is_array($header[$r][$d]))
                    <th
                        @if(array_key_exists('rowspan', $header[$r][$d])) rowspan="{{ $header[$r][$d]['rowspan'] }}"
                        @endif  @if(array_key_exists('colspan', $header[$r][$d])) colspan="{{ $header[$r][$d]['colspan'] }}"
                        @endif
                        style="
                        @if(array_key_exists('bold', $header[$r][$d])) font-weight: bold; @endif
                        @if(array_key_exists('width', $header[$r][$d])) width: {{$header[$r][$d]['width']}}px; @endif
                        @if(array_key_exists('vertical', $header[$r][$d])) vertical-align: {{$header[$r][$d]['vertical']}}; @endif
                            "
                    >
                        {!! $header[$r][$d]['label'] !!}
                    </th>
                @else
                    <th>
                        {!! $header[$r][$d] !!}
                    </th>
                @endif
            @endfor
        </tr>
    @endfor
    </thead>

    <tbody>
    @for($i = 0; $i < count($data); $i++)
        <tr>
            @for($j = 1; $j <= count($data[$i]); $j++)
                @if(is_array($data[$i][$j]))
                    <td style="
                    @if(array_key_exists('bold', $data[$i][$j]))font-weight: bold; @endif
                    @if(array_key_exists('padding', $data[$i][$j])) padding-left: 8px; @endif
                        ">
                        {!! $data[$i][$j]['label'] !!}
                        @if((int)$data[$i][$j]['label'] > 0)
                            @if(array_key_exists('collection', $data[$i][$j]))
                                <input type="hidden" value="{!! $helper->getIds($data[$i][$j]['collection']) !!}">
                            @endif
                        @endif
                    </td>
                @else
                    <td>
                        {!! $data[$i][$j] !!}
                    </td>
                @endif
            @endfor
        </tr>
    @endfor
    </tbody>
</table>
