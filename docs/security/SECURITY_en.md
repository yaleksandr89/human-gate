# Security Policy

## Choose a language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/security/policy) | **Selected** | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_es.md) | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_zh.md) | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_fr.md) | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_de.md) |

## Supported versions

Security fixes are released for the current stable `1.x` line of Human Gate.

| Version | Supported |
| --- | --- |
| `1.x` | Yes |

## What counts as a vulnerability

Potential security issues in Human Gate include:

- accepting an incorrect answer or bypassing CAPTCHA verification;
- accepting the same CAPTCHA again after it has been solved;
- bypassing expiration or the limit on incorrect answers;
- using a CAPTCHA issued for one form to verify a different form with another `Purpose`;
- exposing the correct answer, selected category cards, or icon sequence to the browser;
- corrupting CAPTCHA data when reading or writing a supported PHP session;
- violating the boundaries for untrusted input or image generation with a security impact;
- compromising package source code, dependencies, CI, or release artifacts.

## Security model

Human Gate generates and checks CAPTCHAs on the server. A CAPTCHA is associated with a form or operation key (`Purpose`), has a limited lifetime and number of wrong answers, and cannot be reused after a successful verification.

The package stores verification data on the server. Its built-in storage uses PHP sessions. See the [package security model](https://github.com/yaleksandr89/human-gate/blob/master/docs/security-model_en.md).

## Reporting a vulnerability

Use **GitHub Private Vulnerability Reporting** when the repository provides it:

1. Open the **Security** tab.
2. Open **Advisories**.
3. Select **Report a vulnerability**.
4. Submit the report privately.

If private reporting is unavailable, create an [Issue](https://github.com/yaleksandr89/human-gate/issues/new/choose) requesting a private communication channel **without including vulnerability details**. Do not post a working bypass, exploit code, or sensitive information.

Until coordinated disclosure, do not publish:

- working CAPTCHA bypass instructions or exploit code;
- identifiers and contents of real user sessions, form submissions, or personal information;
- secrets, credentials, private logs, or material from production systems.

## Information to include

Where possible, provide:

- Human Gate version or commit SHA;
- PHP version, `ext-gd` availability, and environment details relevant to reproduction;
- CAPTCHA type: `TextImage`, `CategorySelection`, or `IconSequence`;
- affected operation: `issue()`, `replace()`, `verify()`, image generation, or session storage;
- minimal reproduction steps with synthetic data;
- expected and actual behavior;
- potential impact and a suggested fix, if known.

Use synthetic data. Never attach the contents of real user sessions.

## Report handling

Human Gate is maintained by one person. Reports are reviewed as time permits, without a guaranteed response time. Confirmed issues are corrected with regression checks. Please coordinate disclosure of technical details with the maintainer.
