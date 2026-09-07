# Design System & UI Specifications

## 1. Overview
The Gemini Chat Assistant design system ensures visual harmony with WordPress admin styling on the backend and modern, responsive, non-intrusive aesthetic integration on the frontend.

## 2. Color Palette & Tokens

### 2.1 Admin Dashboard Tokens
- **Background:** `#f0f0f1` (Standard WordPress Admin background)
- **Card Surface:** `#ffffff` (Border radius: 8px, Border: `1px solid #dcdcde`)
- **Primary Accent:** `#2271b1` (WordPress Blue)
- **Primary Hover:** `#135e96`
- **Success:** `#008a20` (Green)
- **Warning:** `#dba617` (Amber)
- **Error:** `#d63638` (Red)
- **Text Primary:** `#1d2327`
- **Text Secondary:** `#646970`

### 2.2 Frontend Chat Widget Tokens
- **Primary Accent (Default):** `#1a73e8` (Google Material Blue, customizable via settings)
- **Widget Surface:** `#ffffff`
- **User Bubble Background:** `#1a73e8`
- **User Bubble Text:** `#ffffff`
- **Bot Bubble Background:** `#f1f3f4`
- **Bot Bubble Text:** `#202124`
- **Border Subdued:** `#e0e0e0`
- **Shadow:** `0 8px 24px rgba(0, 0, 0, 0.15)`
- **Border Radius:**
  - Widget Container: `16px`
  - Floating Trigger: `50%` (Size: 56px x 56px)
  - Message Bubbles: `18px`

## 3. Typography
- **Frontend Font Stack:** `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`
- **Code Snippet Font Stack:** `SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace`
- **Font Sizes:**
  - Title / Header: `16px`, `font-weight: 600`
  - Body Message: `14px`, `line-height: 1.5`
  - Meta / Timestamps / Token counters: `12px`

## 4. UI Layout & Breakpoints
- **Mobile Responsive:** Max width `100vw`, height `100vh` on screens `< 480px`.
- **Desktop Floating Widget:** Width `380px`, Max height `600px`, bottom offset `24px`, right/left offset `24px`.
- **Inline Embed:** Fits 100% of parent container width with a minimum height of `450px`.
