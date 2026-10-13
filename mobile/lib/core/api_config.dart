/// Points at the existing LedgerOne PHP backend (server/api/**). No separate
/// mobile API exists or is needed — the app talks to the same endpoints the
/// web app uses, via the additive mobile_auth bearer-token layer.
///
/// Points at the real deployed server — the Oracle sandbox VM that
/// `.github/workflows/deploy.yml` pushes to on every `main` commit, served
/// at its own domain root (not under a `/ledgerone_erp` subpath, unlike the
/// local XAMPP dev setup). Switch back to a `localhost`/LAN-IP value only
/// for local dev testing against XAMPP.
class ApiConfig {
  static const String baseUrl = 'http://localhost/ledgerone_erp';
}
