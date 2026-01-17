<div class="page">
    <div class="top-row">
        <div class="logo-box">
            @if ($logoFilePath)
                <img src="{{ $logoFilePath }}" alt="" />
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
</div>
