# Lessons Learned

## Database Connection & Last Insert ID
**Issue:** When using `db()->lastInsertId()` immediately after `db()->prepare(...)`, the `lastInsertId()` call was returning `0` even though the insert was successful.
**Cause:** The `db()` helper function performs a liveness check (`SELECT 1`) on every call. If it decides to reconnect (or if the PDO object is not strictly a singleton in a way that preserves state across calls in this specific environment), the second call to `db()` might return a "fresh" connection or reset state, causing `lastInsertId()` to return 0.
**Solution:** Always capture the database connection object in a variable before performing operations that depend on connection state (like transactions or `lastInsertId`).

```php
// ❌ Incorrect
$stmt = db()->prepare('INSERT ...');
$stmt->execute();
$id = db()->lastInsertId(); // Might return 0

// ✅ Correct
$db = db();
$stmt = $db->prepare('INSERT ...');
$stmt->execute();
$id = $db->lastInsertId();
```

## 500 Errors on Successful Operations
**Issue:** The client receives a 500 error, but the operation (e.g., adding an item) actually succeeds in the database.
**Cause:** This usually indicates that the controller logic is interpreting a return value (like `0` from `lastInsertId`) as a failure, or an exception is occurring *after* the database operation but before the response is sent.
**Debugging:** Check the return values of model methods. If a model method returns `0` or `false` when it should return an ID, the controller will likely throw a 500.

## Autoloading
**Issue:** "Class not found" errors after creating new classes.
**Solution:** Always run `composer dump-autoload -o` after adding new classes. If using OPcache, also restart PHP-FPM or call `opcache_reset()`.

## JavaScript Event Delegation
**Issue:** Event listeners attached to elements inside a modal (like close buttons) might not work if the modal HTML is dynamic or if the listeners are attached before the modal exists/is visible.
**Solution:** Use event delegation on a static parent (like `document` or a container) or ensure listeners are attached after the element is definitely in the DOM.
