-- =====================================================================
-- Ramada by Wyndham Multan: Hotel & Restaurant Management System
-- Database structure and starting data.
--
-- How to use: create an empty database (e.g. "hrms") in phpMyAdmin,
-- select it, open the Import tab and import this file.
--
-- Works on MySQL 5.7+ and MariaDB 10.3+ (the versions free hosts use).
-- Text columns that need a unique index are VARCHAR(191) so they fit
-- older MySQL index limits with utf8mb4.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS invoice_items, invoices, order_items, orders, feedback,
    table_reservations, dining_tables, restaurants, menu_items, menu_categories,
    bookings, rooms, room_type_photos, room_types, email_tokens, users,
    contact_messages, gallery_images, site_photos, halls, faqs, live_versions;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------------------

-- role: 'admin' or 'customer'. A 'staff' role can be added later (D2).
-- email_verified_at stays NULL until the customer clicks the email link (FR-AUTH-08).
-- failed_logins / locked_until slow down password guessing (FR-AUTH-10).
CREATE TABLE users (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(100) NOT NULL,
    email             VARCHAR(191) NOT NULL,
    phone             VARCHAR(30)  NOT NULL DEFAULT '',
    password_hash     VARCHAR(255) NOT NULL,
    role              VARCHAR(20)  NOT NULL DEFAULT 'customer',
    email_verified_at DATETIME     NULL,
    failed_logins     TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- wrong passwords in a row
    locked_until      DATETIME     NULL,                    -- after 5 wrong passwords: locked for 10 minutes
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-use 6-digit codes sent by email: confirming a new account, forgot password and
-- changing the password (NFR-10). Only a hash of each code is stored. A code lasts
-- 15 minutes and stops working after 5 wrong tries.
CREATE TABLE email_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    purpose    VARCHAR(20)  NOT NULL,          -- 'verify', 'reset' or 'password'
    token_hash CHAR(64)     NOT NULL,          -- sha256 of user id, purpose and code
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- wrong tries so far
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_tokens_hash (token_hash),
    KEY idx_email_tokens_user (user_id),
    CONSTRAINT fk_email_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Rooms
-- ---------------------------------------------------------------------

-- price_per_night NULL means "not set yet": the room type is shown without a price
-- and can't be booked until the admin enters one.
-- amenities: one amenity per line.
CREATE TABLE room_types (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(100)  NOT NULL,     -- used in links: room.php?type=pool-wing
    name            VARCHAR(100)  NOT NULL,
    description     TEXT          NOT NULL,
    price_per_night DECIMAL(10,2) NULL,
    beds            VARCHAR(100)  NOT NULL,
    feature         VARCHAR(150)  NOT NULL DEFAULT '',
    max_adults      TINYINT UNSIGNED NOT NULL DEFAULT 2,
    max_children    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    amenities       TEXT          NOT NULL,
    sort_order      INT           NOT NULL DEFAULT 0,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_room_types_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos for each room type (FR-ROOM-07, FR-ROOM-08). Files live in assets/images/.
-- is_main marks the large photo at the top of the room page and on the room card.
CREATE TABLE room_type_photos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_type_id INT UNSIGNED NOT NULL,
    file         VARCHAR(255) NOT NULL,
    alt_text     VARCHAR(150) NOT NULL DEFAULT '',
    is_main      TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order   INT          NOT NULL DEFAULT 0,
    KEY idx_room_type_photos_type (room_type_id),
    CONSTRAINT fk_room_type_photos_type FOREIGN KEY (room_type_id) REFERENCES room_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual rooms. status: 'available' or 'maintenance' (FR-ROOM-03).
CREATE TABLE rooms (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_type_id INT UNSIGNED NOT NULL,
    room_number  VARCHAR(10)  NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'available',
    UNIQUE KEY uq_rooms_number (room_number),
    KEY idx_rooms_type (room_type_id),
    CONSTRAINT fk_rooms_type FOREIGN KEY (room_type_id) REFERENCES room_types (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: 'confirmed', 'checked_in', 'checked_out', 'cancelled' (FR-BOOK-08).
-- price_per_night is copied at booking time, so later price changes don't alter old bookings.
-- Double booking (FR-BOOK-03) is checked in PHP: a room is taken if a booking that isn't
-- cancelled has check_in < new check_out AND check_out > new check_in.
CREATE TABLE bookings (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference        VARCHAR(20)   NOT NULL,     -- booking number shown to the guest
    user_id          INT UNSIGNED  NOT NULL,
    room_id          INT UNSIGNED  NOT NULL,
    check_in         DATE          NOT NULL,
    check_out        DATE          NOT NULL,
    adults           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    children         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    price_per_night  DECIMAL(10,2) NOT NULL,
    total            DECIMAL(10,2) NOT NULL,
    status           VARCHAR(20)   NOT NULL DEFAULT 'confirmed',
    special_requests TEXT          NULL,
    arrival_time     VARCHAR(20)   NOT NULL DEFAULT '',  -- guest's estimated arrival, e.g. "14:00 – 16:00"
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at     DATETIME      NULL,
    UNIQUE KEY uq_bookings_reference (reference),
    KEY idx_bookings_room_dates (room_id, check_in, check_out),
    KEY idx_bookings_user (user_id),
    KEY idx_bookings_dates (check_in, check_out),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Restaurants and tables
-- ---------------------------------------------------------------------

CREATE TABLE restaurants (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(100) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description TEXT         NOT NULL,
    hours       VARCHAR(255) NOT NULL,
    opens_at    TIME         NOT NULL DEFAULT '12:00:00',  -- first table reservation slot (FR-TBL-02)
    closes_at   TIME         NOT NULL DEFAULT '23:00:00',  -- last seating is 90 minutes before this
    sort_order  INT          NOT NULL DEFAULT 0,
    UNIQUE KEY uq_restaurants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each table has its own QR code (FR-SIG-QR-01).
CREATE TABLE dining_tables (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    table_number  VARCHAR(10)  NOT NULL,
    seats         TINYINT UNSIGNED NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_dining_tables_number (restaurant_id, table_number),
    CONSTRAINT fk_dining_tables_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: 'confirmed', 'completed', 'cancelled'.
-- Double reservation (FR-TBL-04) is checked in PHP: a reservation holds its table for 2 hours,
-- so a table is taken if a reservation that isn't cancelled, on the same date, starts less than
-- 2 hours before or after the new time.
CREATE TABLE table_reservations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    table_id         INT UNSIGNED NOT NULL,
    reservation_date DATE         NOT NULL,
    reservation_time TIME         NOT NULL,
    party_size       TINYINT UNSIGNED NOT NULL,
    status           VARCHAR(20)  NOT NULL DEFAULT 'confirmed',
    notes            VARCHAR(255) NOT NULL DEFAULT '',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_table_reservations_slot (table_id, reservation_date, reservation_time),
    KEY idx_table_reservations_user (user_id),
    CONSTRAINT fk_table_reservations_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_table_reservations_table FOREIGN KEY (table_id) REFERENCES dining_tables (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Menu and food orders
-- ---------------------------------------------------------------------

CREATE TABLE menu_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id  INT UNSIGNED  NOT NULL,
    name         VARCHAR(150)  NOT NULL,
    description  VARCHAR(255)  NOT NULL DEFAULT '',
    price        DECIMAL(10,2) NOT NULL,
    image        VARCHAR(255)  NOT NULL DEFAULT '',
    is_available TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order   INT           NOT NULL DEFAULT 0,
    KEY idx_menu_items_category (category_id),
    CONSTRAINT fk_menu_items_category FOREIGN KEY (category_id) REFERENCES menu_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- order_type: 'room_service', 'dine_in', 'takeaway' (D18).
--   room_service -> booking_id is set (charged to the room, FR-SIG-ROOM-01)
--   dine_in      -> table_id is set (from the QR code or the website)
-- user_id is NULL for walk-in QR orders, which only give a name (FR-SIG-QR-03).
-- status: 'pending', 'preparing', 'ready', 'completed', 'cancelled'.
CREATE TABLE orders (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  NULL,
    guest_name VARCHAR(100)  NOT NULL DEFAULT '',
    order_type VARCHAR(20)   NOT NULL,
    booking_id INT UNSIGNED  NULL,
    table_id   INT UNSIGNED  NULL,
    status     VARCHAR(20)   NOT NULL DEFAULT 'pending',
    notes      VARCHAR(255)  NOT NULL DEFAULT '',
    total      DECIMAL(10,2) NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_status (status, created_at),
    KEY idx_orders_user (user_id),
    KEY idx_orders_booking (booking_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_orders_booking FOREIGN KEY (booking_id) REFERENCES bookings (id),
    CONSTRAINT fk_orders_table FOREIGN KEY (table_id) REFERENCES dining_tables (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Name and price are copied when the order is placed, so menu changes don't alter old orders.
CREATE TABLE order_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED  NOT NULL,
    menu_item_id INT UNSIGNED  NULL,
    item_name    VARCHAR(150)  NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,
    quantity     SMALLINT UNSIGNED NOT NULL,
    line_total   DECIMAL(10,2) NOT NULL,
    KEY idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_menu_item FOREIGN KEY (menu_item_id) REFERENCES menu_items (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Billing
-- ---------------------------------------------------------------------

-- One invoice per stay (room nights + room service, FR-SIG-ROOM-03) or per food order.
-- status: 'unpaid' or 'paid'. Payment is cash at the hotel (D5).
CREATE TABLE invoices (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20)   NOT NULL,
    user_id        INT UNSIGNED  NULL,
    booking_id     INT UNSIGNED  NULL,
    order_id       INT UNSIGNED  NULL,
    total          DECIMAL(10,2) NOT NULL,
    status         VARCHAR(20)   NOT NULL DEFAULT 'unpaid',
    payment_method VARCHAR(20)   NOT NULL DEFAULT '',
    paid_at        DATETIME      NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoices_number (invoice_number),
    KEY idx_invoices_user (user_id),
    CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_invoices_booking FOREIGN KEY (booking_id) REFERENCES bookings (id),
    CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each line of an invoice, e.g. "Room nights: Pool Wing x 3" or "12 Nov: Room service order #45".
CREATE TABLE invoice_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT UNSIGNED  NOT NULL,
    description VARCHAR(255)  NOT NULL,
    item_date   DATE          NULL,
    quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    order_id    INT UNSIGNED  NULL,
    KEY idx_invoice_items_invoice (invoice_id),
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_order FOREIGN KEY (order_id) REFERENCES orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Feedback, reviews and messages
-- ---------------------------------------------------------------------

-- type: 'review' (public, needs a checked-out booking or completed order, FR-SIG-REV-01),
--       'feedback' or 'complaint' (private, D6).
-- The unique keys allow only one review per stay or order (NULLs don't count).
CREATE TABLE feedback (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(20)  NOT NULL,
    booking_id  INT UNSIGNED NULL,
    order_id    INT UNSIGNED NULL,
    rating      TINYINT UNSIGNED NULL,       -- 1 to 5
    comment     TEXT         NOT NULL,
    is_hidden   TINYINT(1)   NOT NULL DEFAULT 0,   -- admin can hide a review (FR-SIG-REV-04)
    is_resolved TINYINT(1)   NOT NULL DEFAULT 0,   -- for complaints (FR-FB-03)
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_feedback_booking (booking_id),
    UNIQUE KEY uq_feedback_order (order_id),
    KEY idx_feedback_type (type, is_hidden, created_at),
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_feedback_booking FOREIGN KEY (booking_id) REFERENCES bookings (id),
    CONSTRAINT fk_feedback_order FOREIGN KEY (order_id) REFERENCES orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages from the Contact page and event enquiries from Meeting & Events (FR-WEB-05, D13).
-- topic: 'general', 'rooms', 'dining', 'events', 'feedback'.
CREATE TABLE contact_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(191) NOT NULL,
    phone      VARCHAR(30)  NOT NULL DEFAULT '',
    topic      VARCHAR(20)  NOT NULL DEFAULT 'general',
    subject    VARCHAR(150) NOT NULL,
    message    TEXT         NOT NULL,
    is_handled TINYINT(1)   NOT NULL DEFAULT 0,
    handled_at DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact_messages_new (is_handled, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live updates (FR-LIVE-01): one number per table, raised by one each time a page changes it.
-- Open pages ask live.php for these numbers and update themselves when one of theirs moves.
CREATE TABLE live_versions (
    table_name VARCHAR(64)  NOT NULL PRIMARY KEY,
    version    INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event halls on the Meeting & Events page (FR-WEB-07), edited in the admin panel.
-- Sizes in metres and square metres, as given by the hotel.
CREATE TABLE halls (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    description VARCHAR(255)  NOT NULL DEFAULT '',
    area        DECIMAL(7,1)  NOT NULL,
    height      DECIMAL(4,1)  NOT NULL,
    length      DECIMAL(5,1)  NOT NULL,
    width       DECIMAL(5,1)  NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions and answers on the Contact page (FR-WEB-20), edited in the admin panel.
CREATE TABLE faqs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question   VARCHAR(200) NOT NULL,
    answer     TEXT         NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Photos managed by the admin
-- ---------------------------------------------------------------------

-- category: Banquet, Health & Recreation, Lobby, Restaurants, Rooms, Multan (gallery tabs).
-- Each photo has a small copy for the grid in assets/images/gallery/thumbs/ (same path).
CREATE TABLE gallery_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category   VARCHAR(50)  NOT NULL,
    file       VARCHAR(255) NOT NULL,
    caption    VARCHAR(150) NOT NULL DEFAULT '',
    credit     VARCHAR(255) NOT NULL DEFAULT '',   -- photographer and license, for photos that aren't the hotel's
    source_url VARCHAR(255) NOT NULL DEFAULT '',   -- where a credited photo comes from
    sort_order INT          NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_gallery_images_category (category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos on the information pages that the admin can change (FR-WEB-10).
-- location says where the photo appears, e.g. 'home_slide' (several, in sort_order)
-- or 'home_welcome' (one).
CREATE TABLE site_photos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location   VARCHAR(50)  NOT NULL,
    file       VARCHAR(255) NOT NULL,
    alt_text   VARCHAR(150) NOT NULL DEFAULT '',
    sort_order INT          NOT NULL DEFAULT 0,
    KEY idx_site_photos_location (location, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- Starting data
-- Real hotel content comes from design.md section 6.
-- Rows marked SAMPLE are placeholders: replace them from the admin panel.
-- =====================================================================

-- Admin account. Email: admin@ramadamultan.com  Password: ChangeMe@123
-- Change this password straight after the first login.
INSERT INTO users (name, email, phone, password_hash, role, email_verified_at) VALUES
('Hotel Admin', 'admin@ramadamultan.com', '+92 61 454 0877',
 '$2y$12$xVMpQcGXM18.yD6XULS/a.XX.Jp7qLVH7C8MOk3T1vCS2AflnszKe', 'admin', NOW());

-- Room types. Prices left NULL are not confirmed by the hotel yet (design.md section 6).
-- max_children is a PLACEHOLDER of 1 until the hotel confirms its child policy (requirements.md, Q3).
SET @amenities = 'Pipe-system air-conditioning with temperature control\nSmart Android LED TV\nFree WiFi\nIn-room safe (fits a laptop)\n24/7 room service\nMinibar\nIron and ironing board\nComplimentary tea, coffee, white and brown sugar, 2 water bottles\nBusiness desk\nAccess to swimming pool and health club\nLaundry on request';

INSERT INTO room_types (id, slug, name, description, price_per_night, beds, feature, max_adults, max_children, amenities, sort_order) VALUES
(1, 'presidential-suite', 'Presidential Suite',
 'Our largest suite, with a living room that seats 15 to 20 people, a kitchenette and a private balcony. Guests have access to the Executive Lounge.',
 NULL, '1 King Size Bed', 'Access to Executive Lounge', 2, 1, @amenities, 1),
(2, 'business-room', 'King Bed, Business Room',
 'A king bed room with a business desk, made for working travellers. Guests have access to the Executive Lounge.',
 NULL, '1 King Size Bed', 'Access to Executive Lounge', 3, 1, @amenities, 2),
(3, 'deluxe-queen', 'Deluxe Queen Room',
 'A comfortable queen bed room for couples and solo travellers.',
 NULL, '1 Queen Size Bed', '', 2, 1, @amenities, 3),
(4, 'executive-suite', 'Executive Suite',
 'A spacious suite with its own balcony.',
 69995.00, '1 Queen Size Bed', 'Balcony', 3, 1, @amenities, 4),
(5, 'twin-bed', 'Twin Bed',
 'Two single beds, ideal for friends or colleagues travelling together.',
 24995.00, '2 Single Beds', '', 3, 1, @amenities, 5),
(6, 'pool-wing', 'Pool Wing',
 'A room in the pool wing with a balcony overlooking the swimming pool.',
 23995.00, '1 King Size Bed', 'Balcony with pool view', 3, 1, @amenities, 6);

-- Main photo per room type. TEMPORARY small copies from ramadamultan.com;
-- replace them with the hotel's original files from the admin panel.
INSERT INTO room_type_photos (room_type_id, file, alt_text, is_main, sort_order) VALUES
(1, 'rooms/presidential-suite.jpg', 'Presidential Suite', 1, 1),
(2, 'rooms/business-room.jpg', 'King Bed, Business Room', 1, 1),
(3, 'rooms/deluxe-queen.jpg', 'Deluxe Queen Room', 1, 1),
(4, 'rooms/executive-suite.jpg', 'Executive Suite', 1, 1),
(5, 'rooms/twin-bed.jpg', 'Twin Bed', 1, 1),
(6, 'rooms/pool-wing.jpg', 'Pool Wing', 1, 1);

-- SAMPLE room numbers. Replace with the hotel's real rooms from the admin panel.
INSERT INTO rooms (room_type_id, room_number) VALUES
(1, '601'),
(2, '501'), (2, '502'), (2, '503'), (2, '504'),
(3, '401'), (3, '402'), (3, '403'), (3, '404'),
(4, '602'), (4, '603'),
(5, '301'), (5, '302'), (5, '303'), (5, '304'),
(6, '101'), (6, '102'), (6, '103'), (6, '104');

-- Restaurants (real names, descriptions and hours).
INSERT INTO restaurants (id, slug, name, description, hours, opens_at, closes_at, sort_order) VALUES
(1, 'buzz-76', 'Buzz 76',
 'All-day dining from breakfast to midnight supper, with a lunch and dinner buffet of international and local cuisine.',
 '6:30 AM – 11:30 PM; Sunday brunch 11:30 AM – 2:30 PM', '06:30:00', '23:30:00', 1),
(2, 'cafe-saints', 'Cafe Saints',
 'A modern outdoor cafe by the pool, serving international and traditional dishes all day.',
 '3:00 PM – 11:00 PM', '15:00:00', '23:00:00', 2),
(3, 'executive-lounge', 'Executive Lounge',
 'Fresh cuisine in a warm, comfortable setting.',
 '8:00 AM – 11:00 PM', '08:00:00', '23:00:00', 3);

-- SAMPLE dining tables. Replace with the real table plan from the admin panel.
INSERT INTO dining_tables (restaurant_id, table_number, seats) VALUES
(1, '1', 2), (1, '2', 2), (1, '3', 4), (1, '4', 4), (1, '5', 4), (1, '6', 6), (1, '7', 6), (1, '8', 8),
(2, '1', 2), (2, '2', 4), (2, '3', 4), (2, '4', 6),
(3, '1', 2), (3, '2', 4), (3, '3', 4);

-- SAMPLE menu. Ramada's site has no menu, so these items and prices are placeholders
-- for testing. Replace them with the hotel's real menu from the admin panel.
INSERT INTO menu_categories (id, name, sort_order) VALUES
(1, 'Breakfast', 1),
(2, 'Pakistani', 2),
(3, 'Continental', 3),
(4, 'Chinese', 4),
(5, 'Desserts', 5),
(6, 'Drinks', 6);

INSERT INTO menu_items (category_id, name, description, price, sort_order) VALUES
(1, 'Halwa Puri', 'Puri with chana and suji halwa', 1200.00, 1),
(1, 'Continental Breakfast', 'Eggs your way, toast, sausages and baked beans', 1800.00, 2),
(2, 'Chicken Karahi', 'Half, cooked in a wok with tomatoes and green chillies', 2600.00, 1),
(2, 'Mutton Biryani', 'Basmati rice layered with spiced mutton', 2200.00, 2),
(2, 'Daal Makhni', 'Black lentils slow-cooked with butter and cream', 1400.00, 3),
(3, 'Grilled Chicken', 'With mashed potatoes, vegetables and pepper sauce', 2400.00, 1),
(3, 'Club Sandwich', 'With fries', 1500.00, 2),
(4, 'Chicken Manchurian', 'With egg fried rice', 2100.00, 1),
(4, 'Chicken Chowmein', 'Stir-fried noodles with vegetables', 1800.00, 2),
(5, 'Sohan Halwa Kulfi', 'Multan''s famous halwa with kulfi', 900.00, 1),
(5, 'Chocolate Brownie', 'Warm, with vanilla ice cream', 950.00, 2),
(6, 'Mango Shake', 'Made with Multani mangoes (in season)', 650.00, 1),
(6, 'Fresh Lime', 'Sweet or salty', 450.00, 2),
(6, 'Doodh Patti Chai', 'Pot for one', 350.00, 3);

-- Photos on the Home page (FR-WEB-10). TEMPORARY copies from ramadamultan.com
-- (permission D11); replace them with the hotel's original files.
INSERT INTO site_photos (location, file, alt_text, sort_order) VALUES
('home_slide', 'slides/slide-1.jpg', 'Executive Suite bedroom', 1),
('home_slide', 'slides/slide-2.jpg', 'Swimming pool at night', 2),
('home_slide', 'slides/slide-3.jpg', 'Hotel lobby lounge', 3),
('home_slide', 'slides/slide-4.jpg', 'Suite living room', 4),
('home_welcome', 'home/lobby.jpg', 'Hotel lobby lounge', 1),
('home_welcome_2', 'home/reception.jpg', 'Reception desk', 1),
('home_dining', 'home/cafe-saints.jpg', 'Cafe Saints by the pool at night', 1),
('home_multan', 'home/multan.jpg', 'Shrine of Shah Rukn-e-Alam, Multan', 1),
('facility_restaurant', 'facilities/restaurant.jpg', 'Buzz 76 restaurant', 1),
('facility_meeting', 'facilities/meeting.jpg', 'Meeting room', 1),
('facility_pool', 'facilities/pool.jpg', 'Swimming pool', 1),
('facility_lounge', 'facilities/executive-lounge.jpg', 'Executive Lounge', 1),
-- The hotel building with its RAMADA sign (for the About and Contact pages)
('exterior', 'home/exterior.jpg', 'Ramada by Wyndham Multan building', 1);

-- Dining page (FR-WEB-02). Photos in dining/ are TEMPORARY food photos (D22).
-- Replace them with the hotel's own food photos from the admin panel.
INSERT INTO site_photos (location, file, alt_text, sort_order) VALUES
('dining_hero', 'gallery/restaurants/restaurants-02.jpg', 'Buzz 76 dining hall', 1),
('dining_hero', 'dining/family-dinner.jpg', 'A family sharing karahi, kebabs, biryani and naan', 2),
('dining_hero', 'gallery/restaurants/restaurants-05.jpg', 'Buffet counter', 3),
('dining_hero', 'gallery/restaurants/restaurants-08.jpg', 'Hi-tea spread with cakes and pastries', 4),
('dining_hero', 'gallery/restaurants/restaurants-07.jpg', 'Hi-tea table', 5),
('dining_hero', 'dining/chef-plating.jpg', 'A chef plating a dish', 6),
('dining_hero', 'gallery/restaurants/restaurants-11.jpg', 'Executive Lounge', 7),
('dining_hero', 'gallery/restaurants/restaurants-10.jpg', 'Live buffet stations', 8),
('dining_hero', 'gallery/restaurants/restaurants-09.jpg', 'Desserts at hi-tea', 9),
('dining_hero', 'dining/breakfast-buffet.jpg', 'Breakfast pastries', 10),
('dining_hero', 'gallery/restaurants/restaurants-06.jpg', 'Curries at the buffet', 11),
('dining_hero', 'home/cafe-saints.jpg', 'Cafe Saints by the pool at night', 12),
('dining_buzz-76', 'gallery/restaurants/restaurants-02.jpg', 'Buzz 76 dining hall', 1),
('dining_buzz-76', 'gallery/restaurants/restaurants-05.jpg', 'Buffet counter at Buzz 76', 2),
('dining_cafe-saints', 'home/cafe-saints.jpg', 'Cafe Saints by the pool at night', 1),
('dining_cafe-saints', 'facilities/pool.jpg', 'The pool beside Cafe Saints', 2),
('dining_executive-lounge', 'gallery/restaurants/restaurants-11.jpg', 'Executive Lounge', 1),
('dining_executive-lounge', 'gallery/restaurants/restaurants-15.jpg', 'Executive Lounge seating', 2),
('dining_strip', 'dining/family-dinner.jpg', 'Family dinners', 1),
('dining_strip', 'gallery/restaurants/restaurants-10.jpg', 'Live buffet stations', 2),
('dining_strip', 'gallery/restaurants/restaurants-07.jpg', 'Dessert counter', 3),
('dining_strip', 'gallery/restaurants/restaurants-08.jpg', 'Hi-tea', 4),
('dining_strip', 'dining/chef-plating.jpg', 'Cooked to order', 5),
('dining_strip', 'gallery/restaurants/restaurants-03.jpg', 'Buzz 76', 6),
('dining_strip', 'dining/breakfast-buffet.jpg', 'Breakfast buffet', 7),
('dining_strip', 'dining/dining-room.jpg', 'Evenings out', 8),
('dining_reserve', 'gallery/restaurants/restaurants-01.jpg', 'A table laid for dinner at Buzz 76', 1);

-- Meeting & Events page (FR-WEB-07, FR-WEB-10): the hotel's own banquet photos.
INSERT INTO site_photos (location, file, alt_text, sort_order) VALUES
('events_hero', 'gallery/banquet/banquet-01.jpg', 'Banquet hall laid for a dinner', 1),
('events_wedding', 'gallery/banquet/banquet-20.jpg', 'Wedding stage with white flowers and lights', 1),
('events_meeting', 'gallery/banquet/banquet-07.jpg', 'Meeting room with a U-shaped table', 1),
('events_poolside', 'gallery/banquet/banquet-03.jpg', 'Poolside event with flowers on the water', 1),
('events_family', 'gallery/banquet/banquet-14.jpg', 'Round tables laid for a family celebration', 1);

-- Gallery (FR-WEB-03). Hotel photos: TEMPORARY copies from ramadamultan.com's gallery
-- (permission D11); replace with the hotel's originals from the admin panel.
-- Multan photos: Wikimedia Commons, CC BY-SA, credited on the page as their licence requires.
INSERT INTO gallery_images (category, file, caption, credit, source_url, sort_order) VALUES
('Banquet', 'gallery/banquet/banquet-01.jpg', 'Events and weddings', '', '', 1),
('Banquet', 'gallery/banquet/banquet-02.jpg', 'Events and weddings', '', '', 2),
('Banquet', 'gallery/banquet/banquet-03.jpg', 'Events and weddings', '', '', 3),
('Banquet', 'gallery/banquet/banquet-04.jpg', 'Events and weddings', '', '', 4),
('Banquet', 'gallery/banquet/banquet-05.jpg', 'Events and weddings', '', '', 5),
('Banquet', 'gallery/banquet/banquet-06.jpg', 'Events and weddings', '', '', 6),
('Banquet', 'gallery/banquet/banquet-07.jpg', 'Events and weddings', '', '', 7),
('Banquet', 'gallery/banquet/banquet-08.jpg', 'Events and weddings', '', '', 8),
('Banquet', 'gallery/banquet/banquet-09.jpg', 'Events and weddings', '', '', 9),
('Banquet', 'gallery/banquet/banquet-10.jpg', 'Events and weddings', '', '', 10),
('Banquet', 'gallery/banquet/banquet-11.jpg', 'Events and weddings', '', '', 11),
('Banquet', 'gallery/banquet/banquet-12.jpg', 'Events and weddings', '', '', 12),
('Banquet', 'gallery/banquet/banquet-13.jpg', 'Events and weddings', '', '', 13),
('Banquet', 'gallery/banquet/banquet-14.jpg', 'Events and weddings', '', '', 14),
('Banquet', 'gallery/banquet/banquet-15.jpg', 'Events and weddings', '', '', 15),
('Banquet', 'gallery/banquet/banquet-16.jpg', 'Events and weddings', '', '', 16),
('Banquet', 'gallery/banquet/banquet-17.jpg', 'Events and weddings', '', '', 17),
('Banquet', 'gallery/banquet/banquet-18.jpg', 'Events and weddings', '', '', 18),
('Banquet', 'gallery/banquet/banquet-19.jpg', 'Events and weddings', '', '', 19),
('Banquet', 'gallery/banquet/banquet-20.jpg', 'Events and weddings', '', '', 20),
('Banquet', 'gallery/banquet/banquet-21.jpg', 'Events and weddings', '', '', 21),
('Banquet', 'gallery/banquet/banquet-22.jpg', 'Events and weddings', '', '', 22),
('Health & Recreation', 'gallery/health/health-01.jpg', 'Health club', '', '', 23),
('Health & Recreation', 'gallery/health/health-02.jpg', 'Health club', '', '', 24),
('Health & Recreation', 'gallery/health/health-03.jpg', 'Health club', '', '', 25),
('Lobby', 'gallery/lobby/lobby-01.jpg', 'Lobby', '', '', 26),
('Lobby', 'gallery/lobby/lobby-02.jpg', 'Lobby', '', '', 27),
('Lobby', 'gallery/lobby/lobby-03.jpg', 'Lobby', '', '', 28),
('Lobby', 'gallery/lobby/lobby-04.jpg', 'Lobby', '', '', 29),
('Lobby', 'gallery/lobby/lobby-05.jpg', 'Lobby', '', '', 30),
('Lobby', 'gallery/lobby/lobby-06.jpg', 'Lobby', '', '', 31),
('Restaurants', 'gallery/restaurants/restaurants-01.jpg', 'Restaurants', '', '', 32),
('Restaurants', 'gallery/restaurants/restaurants-02.jpg', 'Restaurants', '', '', 33),
('Restaurants', 'gallery/restaurants/restaurants-03.jpg', 'Restaurants', '', '', 34),
('Restaurants', 'gallery/restaurants/restaurants-04.jpg', 'Restaurants', '', '', 35),
('Restaurants', 'gallery/restaurants/restaurants-05.jpg', 'Restaurants', '', '', 36),
('Restaurants', 'gallery/restaurants/restaurants-06.jpg', 'Restaurants', '', '', 37),
('Restaurants', 'gallery/restaurants/restaurants-07.jpg', 'Restaurants', '', '', 38),
('Restaurants', 'gallery/restaurants/restaurants-08.jpg', 'Restaurants', '', '', 39),
('Restaurants', 'gallery/restaurants/restaurants-09.jpg', 'Restaurants', '', '', 40),
('Restaurants', 'gallery/restaurants/restaurants-10.jpg', 'Restaurants', '', '', 41),
('Restaurants', 'gallery/restaurants/restaurants-11.jpg', 'Restaurants', '', '', 42),
('Restaurants', 'gallery/restaurants/restaurants-12.jpg', 'Restaurants', '', '', 43),
('Restaurants', 'gallery/restaurants/restaurants-13.jpg', 'Restaurants', '', '', 44),
('Restaurants', 'gallery/restaurants/restaurants-14.jpg', 'Restaurants', '', '', 45),
('Restaurants', 'gallery/restaurants/restaurants-15.jpg', 'Restaurants', '', '', 46),
('Restaurants', 'gallery/restaurants/restaurants-16.jpg', 'Restaurants', '', '', 47),
('Rooms', 'gallery/rooms/rooms-01.jpg', 'Rooms', '', '', 48),
('Rooms', 'gallery/rooms/rooms-02.jpg', 'Rooms', '', '', 49),
('Rooms', 'gallery/rooms/rooms-03.jpg', 'Rooms', '', '', 50),
('Rooms', 'gallery/rooms/rooms-04.jpg', 'Rooms', '', '', 51),
('Rooms', 'gallery/rooms/rooms-05.jpg', 'Rooms', '', '', 52),
('Rooms', 'gallery/rooms/rooms-06.jpg', 'Rooms', '', '', 53),
('Rooms', 'gallery/rooms/rooms-07.jpg', 'Rooms', '', '', 54),
('Rooms', 'gallery/rooms/rooms-08.jpg', 'Rooms', '', '', 55),
('Rooms', 'gallery/rooms/rooms-09.jpg', 'Rooms', '', '', 56),
('Rooms', 'gallery/rooms/rooms-10.jpg', 'Rooms', '', '', 57),
('Rooms', 'gallery/rooms/rooms-11.jpg', 'Rooms', '', '', 58),
('Rooms', 'gallery/rooms/rooms-12.jpg', 'Rooms', '', '', 59),
('Rooms', 'gallery/rooms/rooms-13.jpg', 'Rooms', '', '', 60),
('Rooms', 'gallery/rooms/rooms-14.jpg', 'Rooms', '', '', 61),
('Rooms', 'gallery/rooms/rooms-15.jpg', 'Rooms', '', '', 62),
('Rooms', 'gallery/rooms/rooms-16.jpg', 'Rooms', '', '', 63),
('Multan', 'gallery/multan/multan-01.jpg', 'Tomb of Shah Rukn-e-Alam', 'Photo: Smjunaid123 / Wikimedia Commons, CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Shah_Rukn-e-Alam2.jpg', 64),
('Multan', 'gallery/multan/multan-02.jpg', 'Tomb of Shah Rukn-e-Alam', 'Photo: ZainShahid117 / Wikimedia Commons, CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Tomb_of_Shah_Rukn-e-Alam_2014-07-31.jpg', 65),
('Multan', 'gallery/multan/multan-03.jpg', 'Shah Rukn-e-Alam at night', 'Photo: Junaidali88z / Wikimedia Commons, CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Shah_Rukn-e-Alam_Tomb.jpg', 66),
('Multan', 'gallery/multan/multan-04.jpg', 'Inside the dome of Shah Rukn-e-Alam', 'Photo: Smjunaid123 / Wikimedia Commons, CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Shah_Rukn-e-Alam_inside.jpg', 67),
('Multan', 'gallery/multan/multan-05.jpg', 'Tile work on Shah Rukn-e-Alam', 'Photo: Kkashaff / Wikimedia Commons, CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Close_up_detail_of_the_facade_of_the_tomb_of_shah_rukn_e_alam.jpg', 68),
('Multan', 'gallery/multan/multan-06.jpg', 'Inside the tomb of Shah Rukn-e-Alam', 'Photo: Junaidali88z / Wikimedia Commons, CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Inside_the_tomb_of_Rukn-e-alam.jpg', 69),
('Multan', 'gallery/multan/multan-07.jpg', 'Ghanta Ghar clock tower', 'Photo: Junaidahmadj / Wikimedia Commons, CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Clock_Tower_-_Ghanta_Ghar,_Multan_-_Multan_Pakistan.jpg', 70),
('Multan', 'gallery/multan/multan-08.jpg', 'Ghanta Ghar at dusk', 'Photo: Tahsin Shah / Wikimedia Commons, CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:GHANTA_GHAR_MULTAN.jpg', 71),
('Multan', 'gallery/multan/multan-09.jpg', 'St. Mary''s Cathedral, Multan', 'Photo: Tahsin Shah / Wikimedia Commons, CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:CATHEDRAL_OF_St._MARY_THE_VIRGIN_CHURCH_MULTAN.jpg', 72);

-- Event halls (real sizes from ramadamultan.com/meeting-events).
INSERT INTO halls (name, description, area, height, length, width, sort_order) VALUES
('Zainab Hall', 'Our largest hall, for weddings, walimas and big conferences.', 542.7, 2.7, 24.6, 22.1, 1),
('Maryam Hall', 'A high-ceilinged hall for receptions, exhibitions and seminars.', 194.9, 4.2, 15.7, 12.4, 2),
('Mehfil Hall', 'A smaller hall for family events, trainings and workshops.', 79.2, 2.8, 8.7, 9.1, 3),
('Zakariya Board Room', 'A private board room for meetings and interviews.', 33.1, 2.6, 7.3, 4.6, 4);

-- Questions on the Contact page. Answers follow the hotel's policies (design.md section 6);
-- the admin can change them on the FAQ page.
INSERT INTO faqs (question, answer, sort_order) VALUES
('What are the check-in and check-out times?', 'Check-in is from 2:00 PM and check-out is by 12:00 PM. If you arrive early or need to leave late, call the front desk and we will do our best to help.', 1),
('Can I cancel my booking?', 'Yes. Cancel free of charge until 6:00 PM on the day you arrive, from My Account or by calling us. After that, one night is charged.', 2),
('Do I pay online?', 'No. You book online and pay at the hotel, in cash or by card at the front desk.', 3),
('Is breakfast included?', 'Yes, every stay includes the breakfast buffet at Buzz 76.', 4),
('Is there parking?', 'Yes, parking at the hotel is free for guests.', 5),
('Do you have an airport shuttle?', 'Yes. Please tell us your flight when you book or call the front desk, and we will confirm the pick-up and any charge.', 6),
('Can children stay?', 'Yes. Each room page shows how many adults and children the room is for. Ask us about extra beds or cots.', 7),
('Can I hold a wedding or meeting at the hotel?', 'Yes. We have four halls, from a board room to Zainab Hall. Send an enquiry from the Meeting & Events page and our events team will get back to you.', 8);
