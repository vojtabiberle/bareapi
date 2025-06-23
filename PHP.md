# Development Guidelines for PHP Projects

## Core Philosophy

**TEST-DRIVEN DEVELOPMENT IS NON-NEGOTIABLE.** Every single line of production code must be written in response to a failing test. No exceptions. This is not a suggestion—it is the fundamental practice that enables all other principles in this document.

We follow Test-Driven Development (TDD) using PHPUnit with a strong emphasis on behavior-driven testing, strict typing, and immutable practices. All work should be done in small, incremental changes, ensuring that the codebase remains in a consistently working state.

**Why?**
Enforces discipline, improves reliability, and ensures every feature is backed by tests.

---

## Quick Reference

**Key Principles:**

- Write tests first (TDD using PHPUnit)
- Test behavior, not implementation
- No usage of loose types; use strict types and explicit type hints
- Immutable data whenever possible
- Small, pure functions
- PHPStan in strict mode always (no `@phpstan-ignore-line` or `@phpstan-ignore-next-line`)
- Use actual PHPStan-compatible schemas/types in tests

**Preferred Tools:**

- **Language**: PHP 8.1+ with `declare(strict_types=1);`
- **Testing**: PHPUnit
- **Static Analysis**: PHPStan
- **Array Operations**: `array_map`, `array_filter`, `array_reduce`, etc.

---

## Testing Principles

### Behavior-Driven Testing

- **No "unit tests"** – tests must verify expected behavior instead of internal implementation.
- Test through the public API exclusively; internals are invisible to tests.
- Maintain a mapping of tests to business scenarios rather than to specific files or methods.
- **Coverage Targets:** Aim for 100% coverage based on business behavior, not implementation details.
- Tests document expected business behavior.

**Why?**
This ensures that refactoring does not break business requirements and that tests remain valid even when internal implementations evolve.

### Testing Tools

- **PHPUnit** is used as the test framework.
- **PHPStan** is enforced for static analysis.
- All test code must follow the same strict typing and standards as production code.

---

## Test Organization

A typical project structure is:

```plaintext
src/
  Payment/
    PaymentProcessor.php
    PaymentValidator.php
tests/
  Payment/
    PaymentProcessorTest.php  // Tests focus on business behavior, not internal implementation details
```

**Why?**
Clear separation of production and test code improves maintainability and makes it straightforward to locate business logic versus tests.

---

## Test Data Pattern

Use factory functions (or static builder classes) for generating test data. Example in PHP:

```php
<?php declare(strict_types=1);

function getMockPaymentRequest(array $overrides = []): array {
    $default = [
        'cardAccountId' => '1234567890123456',
        'amount' => 100,
        'source' => 'Web',
        'accountStatus' => 'Normal',
        'lastName' => 'Doe',
        'dateOfBirth' => '1980-01-01',
        'payingCardDetails' => [
            'cvv' => '123',
            'token' => 'token',
        ],
        'addressDetails' => getMockAddressDetails(),
        'brand' => 'Visa',
    ];
    return array_merge($default, $overrides);
}

function getMockAddressDetails(array $overrides = []): array {
    $default = [
        'houseNumber' => '123',
        'houseName' => 'Test House',
        'addressLine1' => 'Test Address Line 1',
        'addressLine2' => 'Test Address Line 2',
        'city' => 'Test City',
    ];
    return array_merge($default, $overrides);
}
```

**Why?**
Consistent default values and the ability to override specific fields make tests reliable and easier to set up.

---

## Strict Mode Requirements

Every PHP file, especially those with code examples, must begin with:

```php
<?php declare(strict_types=1);
```

Enforce strict type checking and avoid using loose types. Configure PHPStan to disallow any `@phpstan-ignore-line` or `@phpstan-ignore-next-line` usage.

**Why?**
Strict typing prevents many common bugs and makes the code easier to understand and refactor.

---

## Type Definitions

- **Use explicit type hints for properties, arguments, and return types.**
- Primitive types like `string`, `int`, and `float` should be used wherever possible.
- Use `mixed` only when absolutely unavoidable.
- For complex type definitions (e.g., array shapes), use docblock annotations:

```php
<?php declare(strict_types=1);

/**
 * @phpstan-type AddressDetails array{
 *    houseNumber: string,
 *    houseName?: string,
 *    addressLine1: string,
 *    addressLine2?: string,
 *    city: string
 * }
 */
```

**Why?**
This approach increases type safety and clarity, essential for large, maintainable codebases.

---

## Functional Programming

Adopt a "functional light" style:

- Use immutable data patterns.
- Use pure functions and avoid side effects where possible.
- Utilize PHP’s array functions like `array_map`, `array_filter`, and `array_reduce` instead of imperative loops.

### Examples of Functional Patterns

**Don’t:**

```php
<?php declare(strict_types=1);

function applyDiscount(array $order, float $discountPercent): array {
    // Imperative approach with side effects
    foreach ($order['items'] as &$item) {
        $item['price'] = $item['price'] * (1 - $discountPercent / 100);
    }
    $order['totalPrice'] = array_reduce($order['items'], function ($sum, $item) {
        return $sum + $item['price'];
    }, 0);
    return $order;
}
```

**Do:**

```php
<?php declare(strict_types=1);

function applyDiscount(array $order, float $discountPercent): array {
    $items = array_map(
        fn(array $item): array => array_merge($item, [
            'price' => $item['price'] * (1 - $discountPercent / 100)
        ]),
        $order['items']
    );
    $totalPrice = array_reduce($items, fn(float $sum, array $item): float => $sum + $item['price'], 0);
    return array_merge($order, ['items' => $items, 'totalPrice' => $totalPrice]);
}
```

**Why?**
Promotes code that is easier to test and reason about by avoiding mutable state.

---

## Code Structure & Naming

- **No nested if/else statements:** Use early returns and guard clauses.
- **Keep functions small:** Each function should have a single responsibility.
- **Composition over inheritance:** Prefer interfaces for defining service contracts.

### Naming Conventions

- **Classes:** PascalCase (e.g., `PaymentProcessor`)
- **Methods:** camelCase (e.g., `calculateTotal`)
- **Constants:** UPPER_SNAKE_CASE
- **Files:** kebab-case.php or PascalCase.php as long as PSR-12 is followed.

### Example: Naming Conventions

**Don’t:**

```php
<?php declare(strict_types=1);

class paymentprocessor {
    public function calculate_total($order) {
        // ...
    }
}
```

**Do:**

```php
<?php declare(strict_types=1);

namespace App\Payment;

class PaymentProcessor
{
    public function calculateTotal(array $order): float
    {
        // ...
    }
}
```

**Why?**
Following PSR-12 makes the code more consistent, predictable, and easier to navigate.

---

## TDD Process - THE FUNDAMENTAL PRACTICE

1. **Write a failing test:** Begin by writing a PHPUnit test that defines the desired behavior.
2. **Write the simplest code to pass the test:** Implement only what is necessary.
3. **Refactor:** Refine the code with the safety net of passing tests.

### TDD Example Workflow

**Goal:** Add a feature to calculate the total price of a shopping cart.

1. **Write failing test (`tests/ShoppingCartTest.php`):**

```php
<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\ShoppingCart\Cart;
use App\ShoppingCart\Calculator;

final class ShoppingCartTest extends TestCase
{
    public function testCalculatesCartTotal(): void
    {
        $cart = new Cart([
            ['id' => 1, 'name' => 'Apple', 'price' => 0.50],
            ['id' => 2, 'name' => 'Banana', 'price' => 0.30],
        ]);
        $calculator = new Calculator();
        $this->assertEquals(0.80, $calculator->calculateTotal($cart));
    }
}
```

2. **Write simplest code to pass (`src/ShoppingCart/Calculator.php`):**

```php
<?php declare(strict_types=1);

namespace App\ShoppingCart;

class Calculator
{
    public function calculateTotal(Cart $cart): float
    {
        return array_reduce($cart->getItems(), fn(float $total, array $item): float => $total + $item['price'], 0.0);
    }
}
```

3. **Refactor:**
Verify tests pass and refactor as needed without changing the external behavior.

**Why?**
This workflow keeps the development process iterative, reducing bugs and ensuring requirements are met.

---

## Refactoring - The Critical Third Step

Refactoring means cleaning up code without altering its public API:

1. **Commit Before Refactoring:**
   Commit the passing state before beginning refactoring.

2. **Extract Useful Abstractions:**
   Identify duplicated or complex logic and create helper functions.

3. **Verify:**
   Run tests after each small refactoring step to ensure behavior remains correct.

### Example Before Refactoring

```php
<?php declare(strict_types=1);

function processOrder(array $order, array $user): array {
    if (count($order['items']) === 0) {
        throw new \Exception("Cannot process empty order.");
    }
    $total = 0;
    foreach ($order['items'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    if ($user['level'] === 'gold') {
        $total *= 0.9;
    } elseif ($user['level'] === 'silver') {
        $total *= 0.95;
    }
    $shippingCost = $total > 50 ? 0 : 5;
    $order['finalTotal'] = $total + $shippingCost;
    $order['shippingCost'] = $shippingCost;
    $order['status'] = 'processed';
    return $order;
}
```

### Refactored Code

```php
<?php declare(strict_types=1);

namespace App\Order;

class OrderProcessor
{
    public function process(array $order, array $user): array
    {
        if (empty($order['items'])) {
            throw new \Exception("Cannot process empty order.");
        }

        $subtotal = $this->calculateSubtotal($order['items']);
        $totalAfterDiscount = $this->applyDiscount($subtotal, $user);
        $shippingCost = $this->calculateShipping($totalAfterDiscount);

        return array_merge($order, [
            'finalTotal'   => $totalAfterDiscount + $shippingCost,
            'shippingCost' => $shippingCost,
            'status'       => 'processed'
        ]);
    }

    private function calculateSubtotal(array $items): float
    {
        return array_reduce($items, fn(float $sum, array $item): float => $sum + ($item['price'] * $item['quantity']), 0.0);
    }

    private function applyDiscount(float $total, array $user): float
    {
        if ($user['level'] === 'gold') {
            return $total * 0.9;
        }
        if ($user['level'] === 'silver') {
            return $total * 0.95;
        }
        return $total;
    }

    private function calculateShipping(float $total): float
    {
        return $total > 50 ? 0.0 : 5.0;
    }
}
```

**Why?**
Decomposing the logic improves readability, facilitates testing, and makes future changes easier to implement.

---

## Commit Guidelines

- **Atomic Commits:** Each commit should represent a single logical change.
- **Commit Message Format:** Follow [Conventional Commits](https://www.conventionalcommits.org/) with prefixes like `feat:`, `fix:`, `refactor:`, `test:`, etc.
- **Imperative Mood:** Use commands for commit messages (e.g., "feat: Add discount calculation").

---

## Pull Request Standards

- **Clear Title and Description:** Explain what and why the change is made.
- **Small and Focused:** Each pull request should be reviewable on its own.
- **All Tests Must Pass:** CI pipelines must be green prior to merging.
- **No Commented-Out Code:** Remove dead or commented code before submitting.

---

## Resources and References

- [PHP: The Right Way](https://phptherightway.com/)
- [PHPStan Documentation](https://phpstan.org/)
- [PHPUnit Documentation](https://phpunit.de/)
- [PSR-12 Coding Style Guide](https://www.php-fig.org/psr/psr-12/)

---

## Summary

- **TDD is Mandatory:** Write tests first using PHPUnit; production code must follow from failing tests.
- **Strict Typing and Code Quality:** Enforce `declare(strict_types=1);` and utilize PHPStan for static analysis.
- **Clean, Self-Documenting Code:** Use clear naming conventions and keep functions small with a focus on immutability.
- **Refactor Continuously:** Improve design without changing business behavior.
- **Structured Workflow:** Follow conventional commits and pull request standards for a coherent development process.
