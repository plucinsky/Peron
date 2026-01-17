<!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Technicky dennik</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", serif; color: #111; margin: 0; }
        .page { padding: 22px 30px; }
        .top-row { display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 18px; }
        .logo-box { width: 120px; height: 110px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { max-width: 100%; max-height: 100%; }
        .header-title { text-align: center; }
        .header-title h1 { font-size: 20px; margin: 0; letter-spacing: 0.6px; text-transform: uppercase; }
        .header-title h2 { font-size: 17px; margin: 6px 0 0; font-weight: normal; }
        .title { text-align: center; margin: 10px 0 12px; font-size: 17px; font-weight: bold; letter-spacing: 0.3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 2px solid #111; padding: 6px 8px; vertical-align: top; font-size: 12.5px; }
        .label { width: 26%; white-space: nowrap; }
        .value { font-weight: bold; }
        .section-title { font-weight: bold; margin: 10px 0 6px; font-size: 13px; }
        .work-desc { border: 2px solid #111; padding: 10px 10px 12px; font-size: 12.5px; min-height: 240px; }
        .work-desc-title { font-weight: bold; margin-bottom: 6px; }
        .work-desc p { margin: 0 0 8px; }
        .footer-table td { height: 52px; }
        .page-break { page-break-before: always; }
        .attachment-title-box { border: 2px solid #111; padding: 10px 12px; text-align: center; font-weight: bold; }
        .attachment-title-box h1 { margin: 0; font-size: 14px; }
        .attachment-meta td { padding: 5px 8px; }
        .attachment-image { width: 100%; display: block; border: 2px solid #111; }
        .attachment-caption { border: 2px solid #111; border-top: none; padding: 6px 10px; font-size: 12px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="top-row">
        <div class="logo-box">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="" />
            @endif
        </div>
        <div class="header-title">
            <h1>SLOVENSKA SPELEOLOGICKA SPOLOCNOST</h1>
            <h2>Jaskyniarska skupina Spisska Bela</h2>
        </div>
    </div>

    <div class="title">TECHNICKY DENNIK c.: {{ $diary->report_number ?? '' }}</div>

    <table>
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
            <td class="label">Pracovna doba:</td>
            <td><span class="value">{{ $diary->work_time }}</span></td>
        </tr>
        <tr>
            <td class="label">Pocasie pocas akcie:</td>
            <td colspan="3"><span class="value">{{ $diary->weather }}</span></td>
        </tr>
        <tr>
            <td class="label">Veduci akcie:</td>
            <td>
                @if ($leader)
                    <span class="value">{{ $leader->first_name }} {{ $leader->last_name }}</span>
                @endif
            </td>
            <td class="label">Ostatni clenovia SSS:</td>
            <td>
                @foreach ($members as $member)
                    <span class="value">- {{ $member->first_name }} {{ $member->last_name }}</span>@if (! $loop->last)<br>@endif
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="label">Ini ucastnici:</td>
            <td colspan="3">
                @foreach ($other_members as $member)
                    <span class="value">- {{ $member->first_name }} {{ $member->last_name }}</span>@if (! $loop->last)<br>@endif
                @endforeach
                @if ($diary->other_participants)
                    <div class="value">{{ $diary->other_participants }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="work-desc">
        <div class="work-desc-title">Popis pracovnej cinnosti:</div>
        {!! $diary->work_description !!}
    </div>

    <table style="margin-top: 8px;">
        <tr>
            <td><span class="value">Pocet priloh: {{ $attachmentsCount }}</span></td>
        </tr>
    </table>

    <table class="footer-table">
        <tr>
            <td colspan="2">Vyhlbene (hlbka) [m]: <span class="value">{{ $diary->excavated_length_m }}</span></td>
        </tr>
        <tr>
            <td colspan="2">Objavene (dlzka) [m]: <span class="value">{{ $diary->discovered_length_m }}</span></td>
        </tr>
        <tr>
            <td colspan="2">Zamerane (dlzka, hlbka) [m]: <span class="value">{{ $diary->surveyed_length_m }}</span> / <span class="value">{{ $diary->surveyed_depth_m }}</span></td>
        </tr>
    </table>

    <table class="footer-table">
        <tr>
            <td>
                Datum a podpis veduceho akcie:
                @if ($diary->leader_signed_at)
                    <span class="value">{{ $formatDate($diary->leader_signed_at) }}</span>
                @endif
            </td>
            <td>
                Datum a podpis veduceho klubu:
                @if ($diary->club_signed_at)
                    <span class="value">{{ $formatDate($diary->club_signed_at) }}</span>
                @endif
            </td>
        </tr>
    </table>

    @if ($attachments->count() > 0)
        @foreach ($attachments as $index => $attachment)
            <div class="page-break"></div>
            <div class="top-row">
                <div class="logo-box">
                    @if ($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="" />
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
                @if ($attachment['data_uri'])
                    <img src="{{ $attachment['data_uri'] }}" alt="" class="attachment-image" />
                @endif
                @if ($attachment['caption'])
                    <div class="attachment-caption">{{ $attachment['caption'] }}</div>
                @endif
            </div>
        @endforeach
    @endif
</div>
</body>
</html>
