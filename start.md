# PROMPT: PWA Internal Office Hub (Laravel + Vue + Vite + MySQL)

## Role & Context
You are an expert Full-stack Developer specializing in High-Performance Web Apps. Build a "Single Page Application" (SPA) feel using **Laravel, Vue 3 (Vite), and Inertia.js**. The goal is an "Internal Office Hub" that functions as a **PWA (Progressive Web App)** so it is lightning-fast, works offline, and has zero full-page reloads.

## Tech Stack & PWA Specs
- **Core:** Laravel 11 + Vue 3 + Vite.
- **Bridge:** Inertia.js (for seamless SPA experience without loading spinners).
- **PWA:** Integrate `vite-plugin-pwa` to enable service workers, manifest, and offline caching.
- **Database:** MySQL.
- **UI:** Tailwind CSS (Modern, clean, "app-like" feel).

## Feature Requirements

### 1. Instant Dashboard (The Hub)
- **Zero-Latency Navigation:** Use Inertia Links to ensure switching between categories is instant.
- **External Link Cards:** Grid of office tools (Email, HRIS, etc.) with external redirect.
- **File Repository:** Categorized list of documents with instant search/filter.
- **PWA "Install" Prompt:** Add a button or prompt so employees can install the web app on their Home Screen (Android/iOS/Windows).

### 2. Admin/Author Panel
- **Fast Uploads:** Integrated file uploader for documents.
- **CRUD Links & Files:** Simple, reactive forms using Vue `v-model` and Inertia `post/put` requests.
- **Flash Notifications:** Use toast notifications for success/error feedback without reloading.

## Technical Implementation Instructions
1. **PWA Setup:** Configure `vite.config.js` with `vite-plugin-pwa` to include a web manifest (icons, theme color, display: standalone).
2. **Inertia Progress:** Customize the Inertia loading bar (top of page) to be very subtle or use skeleton loaders for a "Native App" feel.
3. **Database Schema:** - `links` (title, url, category, icon).
    - `files` (display_name, file_path, category, size).
4. **Offline Support:** Ensure basic app shell and icons are cached so the app opens instantly even on slow networks.

---
**ACTION:** 1. Provide the `vite.config.js` configuration for PWA.
2. Generate the Laravel Controller and Inertia Vue components for the main Dashboard.
3. Create a clean, responsive Tailwind layout that looks like a native mobile app dashboard.