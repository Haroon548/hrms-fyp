# Requirements

The build checklist for the Hotel & Restaurant Management System. It is taken from [`SRS Final.pdf`](SRS%20Final.pdf), which stays the formal document. This file is the working list: what to build, where it comes from in the SRS, and what has been decided.

**Priority:** **MVP** = must be in the first working version. **Later** = nice to have once the MVP works. **Future** = out of scope; listed as future work in the SRS.

**Status:** ☐ not started · ◐ in progress · ☑ done

**Build order:** authentication → rooms → room booking → restaurant and food orders → table reservation → billing → feedback → admin dashboard.

---

## Decisions

Choices made where the SRS is vague, conflicting, or not practical for this project.

| # | Decision | Reason |
|---|---|---|
| D1 | Backend is plain PHP (no framework), with MySQL and Bootstrap. | The SRS lists "PHP / Java / Python / .NET" and an optional Laravel or Django. Plain PHP is the easiest for the client to set up and host for free, and for the team to explain. |
| D2 | Roles are **admin** and **customer** for now. The `role` column is designed so a **staff** role can be added later without redoing the database. | The SRS mentions hotel manager, restaurant staff and business owner. For this version the admin does all management work. |
| D3 | Password reset is done by the admin from the admin panel. The "Forgot password" page tells customers to contact the hotel. | Free hosting can't send email (PHP `mail()` doesn't work), so reset links can't be emailed. |
| D4 | Confirmations (bookings, orders, reservations) are shown on screen, not emailed. | Same reason as D3. |
| D5 | Payment is recorded by the admin as cash / "paid". There is no online payment gateway. | The SRS lists the gateway as future work. |
| D6 | Complaints use the feedback form, with a type of "Feedback" or "Complaint". | Covers "Complaint Submission" (SRS 5.8) without a separate module. |
| D7 | Invoices are printed using the browser's print button on a print-friendly invoice page. | Covers "Invoice Printing" (SRS 5.7) without a PDF library. |
| D8 | Database backups are a manual export from phpMyAdmin, with steps in the setup guide. | Free hosting has no scheduled jobs. Covers SRS NFR 9 as far as is practical. |

---

## Functional requirements

### Authentication (SRS 3.1.1, 3.3.1, 5.3)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-AUTH-01 | A visitor can register with name, email, phone and password. Email must be unique. | MVP | ☐ |
| FR-AUTH-02 | A user can log in with email and password. Wrong details show an error message. | MVP | ☐ |
| FR-AUTH-03 | A logged-in user can log out, which ends the session. | MVP | ☐ |
| FR-AUTH-04 | Each user has a role (admin / customer). Admin pages can only be opened by admins. | MVP | ☐ |
| FR-AUTH-05 | Passwords are stored hashed with `password_hash` and checked with `password_verify`. | MVP | ☐ |
| FR-AUTH-06 | The admin can reset a user's password (see D3). | MVP | ☐ |
| FR-AUTH-07 | A logged-in user can change their own password. | Later | ☐ |

### Rooms (SRS 2.4.1, 3.1.2, 5.4)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-ROOM-01 | The admin can add, edit and delete room types (name, description, price per night, capacity, image). | MVP | ☐ |
| FR-ROOM-02 | The admin can add, edit and delete rooms (room number, type, status). | MVP | ☐ |
| FR-ROOM-03 | The admin can set a room's status: available or under maintenance. Rooms under maintenance can't be booked. | MVP | ☐ |
| FR-ROOM-04 | Anyone can browse room types with photos, price and details. | MVP | ☐ |
| FR-ROOM-05 | A customer can enter check-in and check-out dates and see which rooms are free for those dates. | MVP | ☐ |

### Room booking (SRS 2.4.3, 3.1.4, 3.3.2, 5.6, NFR 10)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-BOOK-01 | A logged-in customer can book a free room for chosen dates. | MVP | ☐ |
| FR-BOOK-02 | Check-out must be after check-in, and check-in can't be in the past. | MVP | ☐ |
| FR-BOOK-03 | **No double booking:** a room can't be booked if the dates overlap an existing booking that isn't cancelled. The check runs on the server when the booking is saved. | MVP | ☐ |
| FR-BOOK-04 | The total price is nights × price per night, worked out on the server. | MVP | ☐ |
| FR-BOOK-05 | After booking, a confirmation page shows the booking details. | MVP | ☐ |
| FR-BOOK-06 | A customer can see their booking history. | MVP | ☐ |
| FR-BOOK-07 | A customer can cancel an upcoming booking. | MVP | ☐ |
| FR-BOOK-08 | The admin can see all bookings and change their status (confirmed, checked in, checked out, cancelled). | MVP | ☐ |

### Restaurant and food orders (SRS 2.4.2, 3.1.3, 3.3.3, 5.5)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-MENU-01 | The admin can add, edit and delete menu categories. | MVP | ☐ |
| FR-MENU-02 | The admin can add, edit and delete menu items (name, category, price, description, image, available yes/no). | MVP | ☐ |
| FR-MENU-03 | Anyone can view the menu, grouped by category. | MVP | ☐ |
| FR-ORD-01 | A logged-in customer can pick items and quantities and place an order. | MVP | ☐ |
| FR-ORD-02 | The order total is worked out on the server from current menu prices. | MVP | ☐ |
| FR-ORD-03 | The admin can see all orders and update their status (pending, preparing, ready, completed, cancelled). | MVP | ☐ |
| FR-ORD-04 | A customer can see their order history and each order's current status. | MVP | ☐ |

### Table reservation (SRS 2.4.2, 3.1.4, 3.3.4, 5.6)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-TBL-01 | The admin can add, edit and delete dining tables (table number, seats). | MVP | ☐ |
| FR-TBL-02 | A logged-in customer can pick a date, time and party size and see tables that are free and big enough. | MVP | ☐ |
| FR-TBL-03 | A customer can pick a table and confirm the reservation. | MVP | ☐ |
| FR-TBL-04 | **No double reservation:** a table can't be reserved twice for the same time slot. | MVP | ☐ |
| FR-TBL-05 | A customer can see and cancel their reservations. | MVP | ☐ |
| FR-TBL-06 | The admin can see all reservations and change their status. | MVP | ☐ |

### Billing (SRS 5.7)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-BILL-01 | An invoice can be created for a room booking or a food order, with a unique invoice number. | MVP | ☐ |
| FR-BILL-02 | The invoice shows the customer, the items or nights, and the total. | MVP | ☐ |
| FR-BILL-03 | The admin can mark an invoice as paid (cash). The payment date is saved. | MVP | ☐ |
| FR-BILL-04 | The invoice page can be printed (see D7). | MVP | ☐ |
| FR-BILL-05 | A customer can view their own invoices. | MVP | ☐ |

### Feedback (SRS 5.8)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-FB-01 | A logged-in customer can submit a rating (1–5), a comment and a type (feedback / complaint). | MVP | ☐ |
| FR-FB-02 | The admin can view all feedback and complaints. | MVP | ☐ |
| FR-FB-03 | The admin can mark a complaint as resolved. | Later | ☐ |

### Admin dashboard and reports (SRS 3.1.5, 5.9)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-ADM-01 | The dashboard shows counts: customers, rooms, bookings, orders, reservations. | MVP | ☐ |
| FR-ADM-02 | The dashboard shows total revenue from paid invoices. | MVP | ☐ |
| FR-ADM-03 | The dashboard lists recent bookings, orders and feedback. | MVP | ☐ |
| FR-ADM-04 | The admin can see the list of users and change a user's role. | MVP | ☐ |
| FR-ADM-05 | Revenue and booking reports filtered by date range. | Later | ☐ |

---

## Non-functional requirements (SRS 3.5)

| ID | Requirement | How it's met |
|---|---|---|
| NFR-01 Security | Hashed passwords, login required for customer actions, admin pages checked by role. | `password_hash`, a session check at the top of each protected page. |
| NFR-02 Security | Protection against SQL injection and XSS. | PDO prepared statements for all queries; all output escaped with `htmlspecialchars`. |
| NFR-03 Data integrity | No double bookings or double reservations; invalid input rejected. | Server-side validation; overlap checks (FR-BOOK-03, FR-TBL-04); foreign keys in the database. |
| NFR-04 Usability | Simple, clear navigation; works on phones, tablets and desktops. | Bootstrap responsive layout. |
| NFR-05 Compatibility | Works in Chrome, Firefox, Edge and Safari. | Standard HTML/CSS/JS, tested in each. |
| NFR-06 Performance | Pages respond within 3 seconds under normal use. | Simple queries with indexes. Measured during testing, not claimed in advance. |
| NFR-07 Maintainability | Modular code that is easy to find and change. | One folder per feature; shared code in `includes/`; documented setup. |
| NFR-08 Portability | Runs on XAMPP and on free PHP + MySQL hosting. | No Composer, npm or build step; one config file; one SQL import. |
| NFR-09 Backup | Data can be backed up and restored. | Manual export/import through phpMyAdmin (see D8). |

---

## Future work (out of scope, SRS 4.6 / 7.4)

Mobile app · online payment gateway · AI recommendations · loyalty program · inventory and kitchen management · external booking platform integration · email/SMS notifications · advanced analytics · staff roles (hotel manager, restaurant staff, business owner).

---

## Test cases

To be filled in during and after the build (SRS Chapter 6). Each test links to a requirement ID. Record the real result; don't fill in results before running the test.

| Test ID | Requirement | Scenario | Input | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| | | | | | | |
