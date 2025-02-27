# **Technical Overview**

## **1️⃣ Introduction**
This service is a **simplified payment gateway** that supports **payment authorization, capture, and refund** operations. It integrates multiple payment providers, includes **load balancing**, and enforces **strong input validation, structured logging, and error handling**.

The system follows a **modular monolith architecture** and is built with **Symfony 7**, ensuring scalability, maintainability, and best development practices.

---

## **2️⃣ Tech Stack**

### **Languages & Frameworks**
- **PHP 8.2** – Backend language
- **Symfony 7** – PHP framework for structured and scalable development

### **Libraries & Packages**
- **Monolog** – Logging system for structured logs
- **Redis (Predis)** – Used for **idempotency** and **load balancing**
- **PHPUnit** – Unit testing framework
- **PHPStan (Level 8)** – Static analysis for strict type checking
- **Symfony Validator** – API input validation
- **OpenAPI (Swagger)** – API documentation

### **Development Practices**
- **Test-Driven Development (TDD)**: Unit tests cover key payment functionalities.
- **PHPStan Level 8**: Enforces strict type safety and best practices.
- **Logging with Monolog**: Captures all critical operations (API requests, transactions, failures).
- **Structured Exception Handling**: Custom exceptions for **validation errors, provider failures, and payment failures**.

---

## **3️⃣ Service Architecture & Design**

### **Modular Monolith Approach**
- **Why not Microservices?**  
  - The case study requires a **simplified** payment gateway.
  - **Vestiaire Collective’s actual architecture is modular monolith**, making this approach more relatable.
  - **Scalability is still possible within a monolith**, as long as services are well-structured.

### **Applied Design Patterns**
To ensure clean code and maintainability, the following design patterns were applied:
- **Factory Pattern** → `ProviderFactory`: Dynamically selects the appropriate payment provider.
- **Strategy Pattern** → `ProviderAService` & `ProviderBService`: Encapsulates different payment provider behaviors.
- **Service Layer Pattern** → Separates business logic in `PaymentService` & `ValidationService`.
- **Repository Pattern** → `PaymentRepository` handles **Redis operations** for idempotency.

### **Layered Structure**
| Layer | Responsibility |
|-------|---------------|
| **Controller Layer** | Handles HTTP requests and responses |
| **Service Layer** | Contains the business logic (PaymentService, ValidationService) |
| **Repository Layer** | Manages **Redis storage for transactions** |
| **Provider Layer** | Implements interactions with **Provider A & Provider B** |
| **Security Layer** | Handles authentication via `ApiTokenAuthenticator` & `ApiAuthenticationEntryPoint` |

### **Security & Authentication**
- **API-key-like approach** using a **pre-generated token** stored in `.env`.
- Implemented via:
  - `ApiTokenAuthenticator` → Extracts and validates the token.
  - `ApiAuthenticationEntryPoint` → Handles unauthorized access with JSON responses.

### **Load Balancing & Provider Switching**
- **Load Balancing Strategy:**  
  - 60% of authorizations → **Provider A**  
  - 40% of authorizations → **Provider B**  
- **Provider Switching Mechanism:**  
  - If **PROVIDER_SWITCH=true**, failed transactions in **Provider A** (invalid card) are automatically switched to **Provider B**.

### **Idempotency Handling (Redis)**
- **Prevents duplicate operations**, ensuring:
  - **Capture cannot be executed twice on the same authorization.**
  - **Refund cannot be processed multiple times for the same transaction.**
- **Optimized Redis Usage:**
  - For **Capture & Refund**, we save **two operations** in Redis:
    1. **The operation itself** → Stored as `capture_{transaction_id}` or `refund_{refund_id}`.
    2. **A lookup entry** → Stored as `capture_lookup_{auth_token}` or `refund_lookup_{transaction_id}`.
  - **Why two entries?**
    - **Fast lookup (O(1))** instead of searching through all stored transactions (O(n)).
    - **Ensures transactions cannot be duplicated or reprocessed accidentally.**

---

## **4️⃣ API Endpoints & Usage**

### **Endpoints Overview**
| HTTP Method | Path | Description |
|------------|------|-------------|
| `POST` | `/api/payment/authorize` | Authorizes a payment |
| `POST` | `/api/payment/capture` | Captures a previously authorized payment |
| `POST` | `/api/payment/refund` | Refunds a captured payment |

---

### **Detailed Explanation of Each Endpoint**

#### **1️⃣ Authorize Payment**
- **Request Payload Constraints:**
```json
{
  "card_number": "4111111111111111", // Must be a string, 13-19 digits, starts with 3, 4, 5, or 6
  "expiry_date": "12/25", // Must be MM/YY format, valid till last day of the month
  "cvv": "123", // Must be a string, 3-4 digits
  "amount": 1000 // Must be an integer, greater than zero
}
```
- **Response**
```json
{
  "status": "success",
  "auth_token": "abcdef123456"
}
```
- **Failure Scenarios:**
  - **Invalid card details →** Returns `400 Bad Request`
  - **Authorization failure →** Returns `500 Internal Server Error`

---

#### **2️⃣ Capture Payment**
- **Request Payload Constraints:**
```json
{
  "auth_token": "abcdef123456", // Must be a valid authorization token
  "amount": 1000 // Must be an integer, greater than zero
}
```
- **Response**
```json
{
  "status": "success",
  "transaction_id": "tx123456789"
}
```
- **Failure Scenarios:**
  - **Invalid/missing `auth_token` →** Returns `400 Bad Request`
  - **Capture already processed →** Returns the same transaction ID
  - **Provider failure (10% chance) →** Returns `500 Internal Server Error`

---

#### **3️⃣ Refund Payment**
- **Request Payload Constraints:**
```json
{
  "transaction_id": "tx123456789", // Must be a valid transaction ID
  "amount": 1000 // Must be an integer, greater than zero
}
```
- **Response**
```json
{
  "status": "success",
  "refund_id": "rf123456789"
}
```
- **Failure Scenarios:**
  - **Invalid/missing `transaction_id` →** Returns `400 Bad Request`
  - **Refund already processed →** Returns the same refund ID
  - **Provider failure (10% chance) →** Returns `500 Internal Server Error`

---

## **5️⃣ Security & Authentication**
- The API is **secured using an API-key-like approach**.
- **Each request must include a pre-generated API key** in the `Authorization` header.
- **Example:**
```bash
curl -X POST "http://localhost/api/payment/authorize" \
     -H "Authorization: Bearer YOUR_API_SECRET_KEY" \
     -H "Content-Type: application/json" \
     -d '{"card_number":"4111111111111111","expiry_date":"12/25","cvv":"123","amount":1000}'
```
- The key is stored in **`.env`** and checked in the `ApiTokenAuthenticator`.

---

## **6️⃣ Logging & Error Handling**

### **Logging Strategy**
- **Monolog** is used with **two loggers**:
  1. **Development Logs** (default Symfony logs)
  2. **Operational Logs** (`var/log/payment.log` for key transactions)

### **Custom Exception Handling**
- `InvalidPaymentException`: Raised for **invalid API request inputs**.
- `PaymentException`: Raised for **payment processing failures**.
- `ProviderFailureException`: Raised when **a payment provider fails unexpectedly**.

---

## **7️⃣ Assumptions & Limitations**
_Full details available in the README_

### **Assumptions**
- **Modular Monolith > Microservices** for this case study.
- **API Key approach over JWT** (real-world payment gateways like Stripe use static keys).
- **Provider switching logic** based on `PROVIDER_SWITCH` environment variable.
- **Capture & Refund operations simulate real-world failures (10% failure rate).**
- **Expiry date validation ensures cards remain valid until the last day of the month.**

### **Limitations**
- **Only two providers are implemented.** The architecture supports more but requires further development.
- **No database storage.** Currently, Redis is used to store operations.

---
