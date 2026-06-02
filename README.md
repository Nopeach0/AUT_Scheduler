# Kea Buddy — AUT Scheduler

A web app for Auckland University of Technology students to manage their schedule, connect with classmates, and stay on top of campus events.

---

## Features

- **Calendar & notes** — add, edit, and delete personal schedule notes with categories, times, and reminders
- **Schedule privacy** — set your schedule to Public, Friends only, or Private; the server enforces the rule on every request
- **Friends system** — search for other students, send/accept/reject friend requests, view accepted friends
- **Profile customisation** — set a display name, write a bio, and upload a profile photo (JPEG/PNG/GIF/WebP, max 2 MB)
- **Public profile view** — visit another student's profile at `?user=ID`; see their schedule only if you have permission
- **AUT events** — browse and search upcoming AUT campus events
- **AI assistant** — built-in chat assistant to help with scheduling questions
- **Dark mode** — toggle persisted in `localStorage`
- **Remember me** — optional 30-day session cookie on sign-in

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, Bootstrap 5.3.2, Vanilla JS (ES2020) |
| Backend | PHP 8 (no framework) |
| Database | MySQL-compatible — hosted on InfinityFree (`sql112.infinityfree.com`) |
| Auth | PHP sessions + `password_hash` / `password_verify` |
| Styles | Custom CSS variables + Bootstrap |
| Tests | Custom PHP test runner (no PHPUnit) |

---

## Project Structure

```
AUT_Scheduler/
├── index.html                  # Dashboard / home page
├── calendar.html               # Standalone calendar page
│
├── authentication/
│   ├── signin.html
│   ├── register.html
│   └── forgot-password.html
│
├── files/
│   ├── calendar.html           # In-app calendar
│   ├── profile.html            # Own profile + public profile view (?user=ID)
│   ├── events.html
│   ├── event-detail.html
│   └── ai-assistant.html
│
├── api/
│   ├── auth/
│   │   ├── login.php           # POST — authenticate, start session
│   │   ├── register.php        # POST — create account, auto-login
│   │   ├── logout.php          # POST — destroy session
│   │   ├── check.php           # GET  — return current session user
│   │   ├── forgot-password.php
│   │   └── reset-password.php
│   ├── calendar/
│   │   ├── events.php          # GET/POST/PUT/DELETE calendar notes
│   │   └── reminders.php
│   ├── user/
│   │   ├── profile.php         # GET  — public profile + schedule visibility check
│   │   ├── settings.php        # GET/POST — schedule_visibility setting
│   │   ├── update-profile.php  # GET/POST — display name, bio, avatar upload
│   │   ├── search.php          # GET  — search users by name or email
│   │   └── stats.php
│   ├── friends/
│   │   └── index.php           # GET/POST/DELETE — friend requests & relationships
│   ├── events/
│   │   ├── list.php
│   │   ├── detail.php
│   │   └── scrape-aut.php
│   ├── courses/
│   │   └── list.php
│   ├── ai/
│   │   ├── assistant.php
│   │   ├── nutrition.php
│   │   └── upload.php
│   └── save_note.php
│
├── includes/
│   ├── config.php              # DB credentials (git-ignored — copy from config.example.php)
│   ├── config.example.php      # Template for config.php
│   ├── db.php                  # Connection + auto-migration (creates tables if missing)
│   ├── functions.php           # isValidVisibility(), canViewSchedule()
│   ├── auth-check.php          # Redirect unauthenticated users
│   └── header.php
│
├── assets/
│   ├── css/
│   │   ├── app.css
│   │   ├── global.css
│   │   ├── components.css
│   │   ├── pages.css
│   │   └── responsive.css
│   ├── js/
│   │   ├── app.js
│   │   ├── auth.js
│   │   ├── calendar.js
│   │   ├── profile.js
│   │   ├── events.js
│   │   ├── home.js
│   │   ├── ai-assistant.js
│   │   └── utils.js
│   └── images/
│       └── logo.png
│
├── database/
│   └── kea_buddy.sql           # Full schema (users, calendar_notes, friendships)
│
├── tests/
│   ├── run_tests.php           # Master test runner
│   ├── AuthTest.php
│   ├── CalendarTest.php
│   └── SchedulePrivacyTest.php # TDD unit tests — no DB required
│
└── uploads/
    └── avatars/                # User profile photos (git-ignored)
```

---

## Local Setup

### Requirements

- [XAMPP](https://www.apachefriends.org/) (PHP 8.x + Apache) or any PHP 8 web server
- A MySQL-compatible database (local MySQL, or the live InfinityFree instance)

### Steps

1. **Clone the repo**

   ```bash
   git clone https://github.com/Nopeach0/AUT_Scheduler.git
   ```

2. **Copy the database config**

   ```bash
   cp includes/config.example.php includes/config.php
   ```

   Then edit `includes/config.php` and fill in your database credentials. `config.php` is git-ignored so your credentials are never committed.

3. **Point your web server at the project folder**

   With XAMPP, symlink or copy the project into `C:\xampp\htdocs\AUT_Scheduler`, then visit:

   ```
   http://localhost/AUT_Scheduler/
   ```

4. **Database tables are created automatically**

   `includes/db.php` runs `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE` migrations on every request, so no manual SQL import is needed for a fresh database. If you prefer to import the full schema manually, run:

   ```bash
   mysql -u your_user -p kea_buddy < database/kea_buddy.sql
   ```

5. **Create the uploads directory** (if it doesn't exist)

   ```
   uploads/avatars/
   ```

   Make sure the web server has write permission to that folder.

---

## Database Schema

| Table | Key Columns |
|---|---|
| `users` | `id`, `full_name`, `email`, `password_hash`, `schedule_visibility`, `display_name`, `bio`, `avatar_path` |
| `calendar_notes` | `id`, `user_id`, `title`, `description`, `note_date`, `note_time`, `category`, `reminder_minutes` |
| `friendships` | `id`, `requester_id`, `addressee_id`, `status` (`pending` / `accepted`) |

---

## Running the Tests

Tests run from the command line using PHP — no browser or database connection needed for the schedule privacy tests.

```bash
# Run all test suites
C:\xampp\php\php.exe tests/run_tests.php

# Run schedule privacy tests only
C:\xampp\php\php.exe tests/SchedulePrivacyTest.php
```

The schedule privacy tests (`SchedulePrivacyTest.php`) are pure unit tests. They use dependency injection so no real database is required — friendship checks are replaced with mock closures.

---

## Schedule Privacy Rules

The visibility setting is stored per-user in `users.schedule_visibility` and enforced server-side by `canViewSchedule()` in `includes/functions.php`.

| Setting | Who can see the schedule |
|---|---|
| `public` | Anyone, including unauthenticated visitors |
| `friends` | Only accepted friends (checked via the `friendships` table) |
| `private` | Only the owner |

The owner always sees their own schedule regardless of the setting.

---

## API Overview

All endpoints return JSON. Authentication is session-based.

| Method | Endpoint | Auth required | Description |
|---|---|---|---|
| POST | `api/auth/login.php` | No | Sign in |
| POST | `api/auth/register.php` | No | Create account |
| POST | `api/auth/logout.php` | Yes | Sign out |
| GET | `api/auth/check.php` | No | Check session |
| GET/POST/PUT/DELETE | `api/calendar/events.php` | Yes | Manage calendar notes |
| GET | `api/user/profile.php?id=X` | No | Public profile + schedule |
| GET/POST | `api/user/settings.php` | Yes | Read/update visibility setting |
| GET/POST | `api/user/update-profile.php` | Yes | Read/update display name, bio, avatar |
| GET | `api/user/search.php?q=X` | Yes | Search users |
| GET/POST/DELETE | `api/friends/index.php` | Yes | Friend requests & relationships |

---

## Contributing

1. Create a feature branch from `main`
2. Make your changes and run the test suite before committing
3. Open a pull request — include a short description of what you changed and why

---

## Licence

Built for an AUT student assignment. Not intended for commercial use.
