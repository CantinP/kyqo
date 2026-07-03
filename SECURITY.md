# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| main    | ✅ Yes     |

## Reporting a Vulnerability

We take security seriously. If you discover a vulnerability in Kyqo, **please do not open a public GitHub issue**.

### How to Report

1. **Email**: Send a detailed report to `security@kyqo.dev`
2. **Subject**: `[SECURITY] Brief description`
3. **Include**:
   - A clear description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (optional)

We will acknowledge receipt within **48 hours** and aim to release a patch within **7 days** for critical issues.

## Security Hardening Built Into Kyqo

Kyqo is designed with a **security-first** architecture:

| Layer | Protection |
|-------|------------|
| HTTP Kernel | Global middleware stack: body size limit → security headers → CSRF → rate limiting |
| CSRF | Per-session token with `hash_equals()`, rotation after each request, SameSite=Strict cookie |
| CSP | Per-request nonce, `frame-ancestors 'none'`, `object-src 'none'`, `upgrade-insecure-requests` |
| Rate Limiting | Per-IP per-route throttling with configurable limits, RFC-compliant `Retry-After` headers |
| Request | Host header injection protection, IP validation, bearer token format enforcement, body size cap |
| Response | CRLF injection stripping on all headers, open redirect protection (`javascript:`, `data:` blocked) |
| View Engine | Path traversal protection via `realpath()` + base path assertion, XSS escaping via `e()` |
| IoC Container | Class namespace whitelist, dangerous class blocklist, explicit binding requirement |
| Bootstrap | `APP_KEY` presence and length validation, `APP_DEBUG=true` blocked in production |
| Session | `HttpOnly`, `Secure`, `SameSite=Strict` cookies enforced by default |
| Proxies | `X-Forwarded-For` only trusted for explicitly configured trusted proxy IPs |
| Server | `.htaccess` and `nginx.conf` block dotfiles, sensitive extensions, and non-public directories |

## Responsible Disclosure

We follow the principle of [Responsible Disclosure](https://en.wikipedia.org/wiki/Responsible_disclosure).
Researchers who report valid issues will be credited in the release notes.

## Bug Bounty

There is currently no paid bug bounty program. Recognition and credit will be provided for all valid reports.
