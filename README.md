
# Spaceship Registrar Module for WHMCS

**This repository was archived on Jan 25, 2026.**  
It’s now **read-only**. No further updates will be made.

Reason: too many people (especially in India) were taking open-source code, repackaging it, and selling it. WHMCS also doesn’t care about OSS or protecting developers, so there's no point in maintaining this anymore. Thanks to everyone who used it respectfully.

---

## Overview

A test/experimental WHMCS registrar module for integrating the Spaceship API.  
Not official, not supported, and not guaranteed to work.

---

## Disclaimer

- This was built for **personal/testing/learning** only.  
- Might have bugs, issues, security problems — use at your own risk.  
- No warranty, no support.

---

## Features

- Domain registration  
- Domain transfer  
- Domain renewal  
- Basic templates  
- Simple logging  

---

## Installation

1. Upload the `spaceship` folder to `modules/registrars/` in WHMCS.  
2. Activate the module in WHMCS → Setup → Products/Services → Domain Registrars.  
3. Add your API key/secret in the module config.

---

## Config

- Edit `config.json` for API credentials.  
- Edit `lang/english.php` for language strings.  
- Templates are in `templates/`.

---

## Logs

API requests go to `logs/api.log`.

Example:
```

[2024-11-26 10:30:00] API Request: /domain/register
[2024-11-26 10:30:01] API Response: {"status":"success"}

```

---

## File Structure

```

spaceship/
├── lang/
├── lib/
├── logs/
├── templates/
├── config.json
├── spaceship.php
└── README.md

```

---

## Requirements

- WHMCS 8.x+  
- PHP 7.4+  
- Spaceship API key/secret  

---

## Troubleshooting

- Check `logs/api.log` for API errors  
- Make sure your API credentials are correct  
- Clear template cache if changes don’t show  

---

## Contributing

Repo is archived → no contributions accepted.

---

## Contact

- Email: springmusk@gmail.com  
- Website: https://basantasapkota026.com.np
