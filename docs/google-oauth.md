# Google OAuth (customer “Continue with Google”)

This app uses a **custom OAuth2 flow** (`GoogleLoginController`): authorization code → token exchange → login or register as `Customer`.

**New Google sign-ups** are created **unverified** and receive the same **verification email** as the normal signup form. After clicking the link, they can sign in with Google or password. **Existing verified** customers are logged in immediately after Google consent.

## 1. Environment variables

Set these in `.env` or **`.env.local`** (recommended for secrets; `.env.local` is gitignored):

| Variable | Purpose |
|----------|---------|
| `GOOGLE_CLIENT_ID` | OAuth 2.0 **Web client** ID from Google Cloud |
| `GOOGLE_CLIENT_SECRET` | Client secret |
| `GOOGLE_CALLBACK_URL` | **Exact** callback URL registered in Google (see below) |

Example (local Symfony server on port 8000):

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-secret
GOOGLE_CALLBACK_URL=http://127.0.0.1:8000/connect/google/check
```

- **`DEFAULT_URI`** in `.env` should use the **same scheme/host/port** as you use in the browser (used for CLI URL generation).  
- **Do not mix** `http://localhost:8000` and `http://127.0.0.1:8000` — cookies/sessions differ and you can get **“invalid state”**.

After changes: `php bin/console cache:clear`

## 2. Google Cloud Console

1. Open [Google Cloud Console](https://console.cloud.google.com/) → select or create a project.
2. **APIs & Services** → **OAuth consent screen** — configure (External for testing, add test users if in Testing).
3. **APIs & Services** → **Credentials** → **Create credentials** → **OAuth client ID** → type **Web application**.
4. Under **Authorized redirect URIs**, add **exactly**:
   - Local: `http://127.0.0.1:8000/connect/google/check` **or** `http://localhost:8000/connect/google/check` (match what you use everywhere).
   - Production: `https://your-domain.com/connect/google/check`
5. Copy **Client ID** and **Client secret** into `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.

## 3. Symfony routes (reference)

| Route | Path |
|-------|------|
| `customer_login_google` | `/login/google` — starts OAuth (redirect to Google) |
| `customer_google_check` | `/connect/google/check` — OAuth callback |

`GOOGLE_CALLBACK_URL` must match the callback URL **including** `/connect/google/check`.

## 4. Security

- Routes `/login/google` and `/connect/google/check` are **public** (`config/packages/security.yaml`).
- **Rotate** client secret if it was ever committed or leaked; store production values in secrets / `.env.local` / hosting env, not in git.

## 5. Mobile app (React Native) — JWT API

The Vallejera app uses **native Google Sign-In** and exchanges the device `idToken` for a Lexik JWT (same shape as `POST /api/login`).

| Item | Value |
|------|--------|
| Endpoint | `POST /api/login/google` |
| Body | `{ "idToken": "<from Google Sign-In>", "action": "login" \| "signup" }` |
| Success (login) | `200` + `{ "success": true, "data": { "token": "..." }, "token": "..." }` (top-level `token` kept for older app builds) |
| Sign-up created | `201` + `{ "message": "..." }` (no JWT until email verified) |
| Unverified login | `403` + `{ "message": "..." }` |
| Unknown account on login | `422` + `{ "message": "..." }` |

**Env (optional, for `aud` validation):**

| Variable | Purpose |
|----------|---------|
| `GOOGLE_CLIENT_ID` | Web client ID (Symfony + `webClientId` in React Native) |
| `GOOGLE_ANDROID_CLIENT_ID` | Android OAuth client ID (if different from web) |
| `GOOGLE_IOS_CLIENT_ID` | iOS OAuth client ID |

In Google Cloud Console, create an **Android** OAuth client with package `com.vallejera` and your debug/release **SHA-1**. The React Native app passes `webClientId` = **Web client** ID so the `idToken` `aud` matches `GOOGLE_CLIENT_ID` on the server.

See Vallejera `docs/google-signin-mobile.md` for app setup.

## 6. Mobile JWT bridge (browser OAuth, no native SDK)

The app opens SRU web OAuth with `?platform=app`. After Google consent, the callback issues a Lexik JWT and redirects to:

`vallejera://auth/callback?token=...` (success)  
`vallejera://auth/callback?status=pending&message=...` (signup / verify email)  
`vallejera://auth/callback?error=1&message=...` (failure)

| Start URL | Use |
|-----------|-----|
| `/login/google?platform=app` | Login |
| `/signup/google?platform=app` | Sign up |

**Emulator:** `GOOGLE_CALLBACK_URL` must use the same host the device uses (e.g. `http://10.0.2.2:8000/connect/google/check` when the app calls `10.0.2.2`), and that URI must be registered in Google Cloud Console.

Optional env: `APP_DEEP_LINK_SCHEME` (default `vallejera`).

## 7. Production checklist

- [ ] `GOOGLE_CALLBACK_URL` uses **HTTPS** and matches Google Console redirect URI.
- [ ] OAuth consent screen published (or restricted test users only).
- [ ] `framework.session.cookie_secure` / `auto` works with HTTPS behind a reverse proxy (`trusted_proxies` is already set in this project).
- [ ] Android/iOS OAuth clients registered if shipping the mobile app.
