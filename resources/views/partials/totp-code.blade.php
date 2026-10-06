<div class="form-group">
    <label for="totp_code">Código de Google Authenticator</label>
    <input id="totp_code" name="totp_code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="32" required value="" placeholder="Código de 6 dígitos o recuperación">
    @error('totp_code')<small class="text-danger">{{ $message }}</small>@enderror
</div>
