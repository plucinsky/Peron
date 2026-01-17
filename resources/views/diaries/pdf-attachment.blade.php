<div class="page">
    <div class="top-row">
        <div class="logo-box">
            @if ($logoFilePath)
                <img src="{{ $logoFilePath }}" alt="" />
            @endif
        </div>
        <div class="attachment-title-box">
            <h1>PRILOHA c. {{ $index + 1 }}/{{ $attachmentsCount }} K TECHNICKEMU DENNIKU</h1>
        </div>
    </div>

    <table class="attachment-meta" style="margin-top: 8px;">
        <tr>
            <td class="label">Lokalita:</td>
            <td colspan="3"><span class="value">{{ $diary->locality_name }}</span></td>
        </tr>
        <tr>
            <td class="label">Poloha lokality:</td>
            <td colspan="3"><span class="value">{{ $diary->locality_position }}</span></td>
        </tr>
        <tr>
            <td class="label">Krasove uzemie:</td>
            <td><span class="value">{{ $diary->karst_area }}</span></td>
            <td class="label">Orograficky celok:</td>
            <td><span class="value">{{ $diary->orographic_unit }}</span></td>
        </tr>
        <tr>
            <td class="label">Datum:</td>
            <td><span class="value">{{ $formatDate($diary->action_date) }}</span></td>
            <td class="label">Foto:</td>
            <td>
                @if ($leader)
                    <span class="value">{{ $leader->first_name }} {{ $leader->last_name }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div style="margin-top: 10px;">
        @if ($attachment['file_path'])
            <img src="{{ $attachment['file_path'] }}" alt="" class="attachment-image" />
        @endif
        @if ($attachment['caption'])
            <div class="attachment-caption">{{ $attachment['caption'] }}</div>
        @endif
    </div>
</div>
