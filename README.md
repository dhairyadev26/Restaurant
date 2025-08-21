# Food Chef Cafe Management

A modern, responsive cafe/restaurant website with a public site and an admin panel — curated and maintained by Niyati Raiyani.

Live Demo: https://food-restaurant.infinityfreeapp.com/

## Features

- Dynamic homepage with banners and sections (About, Services, Team, Food, Contact)
- Admin panel for managing banners, services, team, food, photo gallery
- Image lightbox gallery and sliders
- Clean URL routing via `.htaccess`
- Configurable environment with production overrides via `config/secrets.php`

## Screenshots

<p>
  <img src="public/images/slide1.jpg" alt="Homepage Banner" width="640" />
</p>
<p>
  <img src="public/images/about1.jpg" alt="About Section" width="640" />
</p>
<p>
  <img src="public/images/service1.jpg" alt="Services Section" width="640" />
</p>
<p>
  <img src="public/images/team1.jpg" alt="Team Section" width="640" />
</p>
<p>
  <img src="public/images/food1.jpg" alt="Food Gallery" width="640" />
</p>
<p>
  <img src="public/images/contact.jpg" alt="Contact Section" width="640" />
</p>

## Quick start (local, XAMPP)

1. Start Apache and MySQL in XAMPP.
2. Copy this repo into `C:\\xampp\\htdocs\\final` so `htdocs\\final\\index.php` exists.
3. Create a MySQL database (e.g., `hotel`) in phpMyAdmin.
4. Import `database/project.sql` (and optionally `database/migrations/*.sql`).
5. Open `http://localhost/final/` (site) and `http://localhost/final/admin/` (admin).
   - Admin login: username `admin`, password `admin123`.

## Configuration

- `config/config.php` auto-detects `BASEURL` and sets sensible defaults.
- Create `config/secrets.php` to override DB and environment for production:

  ```php
  <?php
  define('HOSTNAME','your-db-host');
  define('USERNAME','your-db-user');
  define('PASSWORD','your-db-pass');
  define('DB','your-db-name');
  define('ENVIRONMENT','production');
  define('DEBUG_MODE',false);
  ?>
  ```

## Deploy to InfinityFree (summary)

1. Upload the contents of `final` into `htdocs/`.
2. Create a MySQL database in the Control Panel and import `database/project.sql` via phpMyAdmin.
3. Add `config/secrets.php` with your DB credentials (see above).
4. Browse your site at your InfinityFree domain.

## Project structure

- `modules/` — frontend modules (banner, about, services, team, food, contact)
- `admin/` — admin panel (modules, public assets)
- `public/` — site assets (css, js, images)
- `libs/` — core libraries (Db wrapper, managers)
- `database/` — SQL dump and migrations
- `config/` — base config and secrets override

## License and credits

© 2025 Food Chef. All rights reserved | Developed by Niyati Raiyani
