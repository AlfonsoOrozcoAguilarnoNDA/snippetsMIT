# 🚀 Automated High-Security Sync Setup Guide (Python Backend)

This guide explains how to prepare your server environment to safely deploy a Python backend using the **High-Security Synchronizer** (`updater.php`) script. 

## ⚠️ This is a Draft

Work in progress 24 nov 2026

## ⚠️ Important Architectural Note

While the core application is a **Python Backend** (e.g., FastAPI, Flask, Django), this deployment mechanism requires a lightweight web server (Apache/Nginx + PHP) *solely* to serve the `updater.php` script securely. 

**OS Recommendation:** **Debian 12/13** is highly recommended for its simplicity and predictable package management. While Rocky Linux/RHEL works, path and permission structures may require slight adjustments.

It is assumed you are working in the final production directory (e.g., `/var/www/api.yourdomain.com`).

---

## 1. System Requirements

Ensure the following packages are installed on your server:

* **Git:** 
  * Debian/Ubuntu: `sudo apt update && sudo apt install git -y`
  * Rocky Linux/RHEL: `sudo dnf install git -y`
* **Python & Venv:** (Required for your app, not the updater)
  * Debian/Ubuntu: `sudo apt install python3 python3-venv -y`

---

## 2. Repository Initialization & Security Setup

Navigate to your web directory and initialize the repository. 

### 2.1. Create a Strict `.gitignore`
Before doing anything, ensure your `.gitignore` explicitly protects local environment files. **If `.env` is committed to the repo, the synchronizer will overwrite it.**
```text
.env
venv/
__pycache__/
*.pyc
*.log
