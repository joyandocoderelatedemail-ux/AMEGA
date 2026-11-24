{{-- Spam traps (App\Support\SpamTrap): people never see or fill "website"; bots fill it, or post seconds after loading the page. --}}
<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
    <label for="trap-website">Website</label>
    <input type="text" id="trap-website" name="website" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="form_started" value="{{ encrypt(now()->timestamp) }}">
