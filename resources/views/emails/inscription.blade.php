{{-- Courriel volontairement sobre : beaucoup le liront depuis un telephone
     modeste, et les clients de messagerie ignorent la moitie du CSS. --}}
<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#0F1720;padding:24px;color:#EAF4FC">
  <div style="max-width:520px;margin:0 auto;background:#111A28;border:1px solid #1E2C3E;border-radius:14px;padding:24px">

    <p style="margin:0;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#0E7490">{{ $signature }}</p>
    <h1 style="margin:8px 0 20px;font-size:22px;color:#22D3FF">{{ $tournoi }}</h1>

    <p style="margin:0 0 16px;font-size:16px">Bonjour {{ $nom }},</p>

    @foreach (preg_split('/\n\s*\n/', trim($corps)) as $paragraphe)
      <p style="margin:0 0 14px;line-height:1.6;color:#93A9BF">{!! nl2br(e($paragraphe)) !!}</p>
    @endforeach

    @if ($debut)
      <p style="margin:20px 0 0;padding:14px;background:#0B111D;border:1px solid #1E2C3E;border-radius:10px">
        <span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#5F7896">Coup d'envoi</span><br>
        <strong style="color:#22D3FF;font-size:16px">{{ $debut }}</strong>
      </p>
    @endif

    <p style="margin:24px 0 0">
      <a href="{{ $site }}" style="display:inline-block;background:#22D3FF;color:#0F1720;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:10px">
        Suivre le tournoi
      </a>
    </p>

    <p style="margin:24px 0 0;font-size:12px;color:#5F7896">{{ $signature }}</p>
  </div>
</div>
