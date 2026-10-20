# Delivery feature removal

Removed dedicated delivery pages, schedule CRUD endpoints, supplier delivery portal and its status-update API, dashboard delivery schedule card, and related navigation. Existing orders, customer ledgers, delivery dates, and historical delivery status/accounting data are intentionally preserved. Shared order status and accounting code remains for backward compatibility; it needs database-backed regression testing before any further deletion. No database tables were dropped.
