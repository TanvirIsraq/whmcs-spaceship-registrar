# User Guide: WHMCS Spaceship.com Registrar Module

Automate your domain registration, renewals, and management with the Spaceship.com Registrar Module for WHMCS.

---

## Table of Contents
- [🚀 Installation & Setup](#-installation--setup)
  - [2. Uploading the Files](#2-uploading-the-files)
- [📖 Standard Features](#-standard-features)
- [💎 Premium (PRO) Features](#-premium-pro-features)
  - [1. Automatic TLD Pricing Sync](#1-automatic-tld-pricing-sync)
  - [2. Premium Domain Support & Markup](#2-premium-domain-support--markup)
  - [3. PRO Status Badge](#3-pro-status-badge)
- [❓ FAQ](#-faq)

---

## 🚀 Installation & Setup

### 1. Requirements
- A Spaceship.com account.
- API Key & Secret (Obtain from [Spaceship API Manager](https://www.spaceship.com/application/api-manager/)).
- **Topeta Helper Addon** (Required for Premium features).

### 1.1 Choose the Correct Package
- **Free Package**: `whmcs-spaceship-registrar-v2.2.3.zip`  
  Available from the public release page of the free repo.
- **Premium Package**: `whmcs-spaceship-registrar-premium-v2.2.3.zip`  
  Available on your Topeta customer portal (https://my.topeta.com/dashboard/).

### 2. Uploading the Files
1. Download the `whmcs-spaceship-registrar-premium-v2.2.3.zip`.
2. Extract the files to your WHMCS root directory.
3. The files should land in `modules/registrars/spaceship/`.

### 2.1 Install the Topeta Helper Addon (Premium Only)
1. Download the Helper Addon package.
2. Extract it to your WHMCS root so it lands in `modules/addons/jobfew_helper/`.
3. In WHMCS Admin, go to **System Settings > Addon Modules**.
4. Find **Topeta Helper** and click **Activate**.
5. Click **Configure**, choose admin roles, and **Save Changes**.
6. Go to **Addons > Topeta Helper / Jobfew Helper**.
7. Enter your license key for **Spaceship Registrar** and click **Save**.
8. The status should show **Active (Trial)** or **Active (Lifetime)**.

### 3. Configuration in WHMCS
1. In WHMCS Admin, go to **System Settings > Domain Registrars**.
2. Find **Spaceship** in the list and click **Activate**.
3. **API Key**: Enter your Spaceship API Key.
4. **API Secret**: Enter your Spaceship API Secret.
5. **Test Mode**: Keep unchecked (Spaceship currently has no public sandbox).

---

## 📖 Standard Features
- **Real-time Availability**: High-speed domain search.
- **Privacy Protection**: Support for ID Protection / WHOIS Privacy.
- **DNS Management**: Manage A, AAAA, CNAME, MX, and TXT records directly from WHMCS.
- **Email Forwarding**: Support for setting up domain aliases.
- **Registrar Lock**: One-click security locking for all domains.
- **EPP Codes**: Customers can retrieve transfer codes directly from their client area.

---

## 💎 Premium (PRO) Features
Activation of the PRO version unlocks advanced automation tools.

### 1. Automatic TLD Pricing Sync
- **Accuracy**: Automatically syncs your TLD costs (Register, Renew, Transfer) with the latest rates from Spaceship.
- **Automatic ICANN Fee**: Pricing includes the $0.18 ICANN fee automatically.
- **How to Sync**:
  1. Go to **System Settings > Domain Pricing**.
  2. Click **Import TLD Pricing**.
  3. Select **Spaceship** as the source.
  4. Click **Import** to pull real-time pricing for over 500+ extensions.
- **Automation**: If enabled, the daily WHMCS cron will keep your prices updated automatically.

### 2. Premium Domain Support & Markup
- **Feature**: Allows your customers to search for and purchase high-value “Premium” domains.
- **Markup Control**: In the module settings, use the **Premium Domain Markup (%)** field to add your desired profit margin (e.g., 10%) on top of the wholesale premium cost.

### 3. PRO Status Badge
- Your module settings page will display a **PRO** badge and a live status line, confirming your license is active and your TLD sync is operational.

---

## ❓ FAQ

### How do I enable the PRO features?
Install the **Topeta Helper / Jobfew Helper Addon** and enter your license key there. Once the Helper Addon shows an **Active (Trial)** or **Active (Lifetime)** status, the Spaceship module will automatically unlock all PRO features.

### My sync isn’t showing any TLDs.
Ensure your license is active in the Topeta Helper Addon. TLD Sync requires a valid Premium license to access the live pricing feed.

### Does it support multi-currency?
Yes. The module detects your WHMCS installation currency and maps the pricing feed accordingly.
