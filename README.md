# 🏦 Payment Gateway Service

🚀 A simplified yet robust **payment gateway** implementation.  
This service provides a **merchant API** to handle **authorization, capture, and refund** operations.  

## 📌 Features:
✅ Secure **API Key-based authentication** (simulating API keys like Stripe).  
✅ Implements **payment providers** with **load balancing (60%-40%)**.  
✅ **Idempotency handling** to prevent duplicate transactions.  
✅ **Redis storage** for transaction state management.  
✅ **Structured logging with Monolog** for operational tracking.  
✅ **Fully tested** with **PHPUnit (100% coverage of main functionalities)**.  
✅ **OpenAPI documentation + Pre-configured Postman Collection**.  

---
## 📌 Table of contents:


- [🏦 Payment Gateway Service](#-payment-gateway-service)
  - [📌 Features:](#-features)
  - [📌 Table of contents:](#-table-of-contents)
  - [📌 **1. Installation \& Setup**](#-1-installation--setup)
    - [**🔹 Prerequisites**](#-prerequisites)
    - [**🔹 Clone the Repository**](#-clone-the-repository)
    - [**🔹 Setup and Run the Project**](#-setup-and-run-the-project)
  - [📌 2. API Endpoints](#-2-api-endpoints)
      - [**API Docs**](#api-docs)
      - [**Postman Pre-configured Collection**](#postman-pre-configured-collection)
      - [**Authentication**](#authentication)
      - [**Descriptions**](#descriptions)
    - [🔹 1️⃣ Authorize Payment](#-1️⃣-authorize-payment)
    - [🔹 2️⃣ Capture Payment](#-2️⃣-capture-payment)
    - [🔹 3️⃣ Refund Payment](#-3️⃣-refund-payment)
  - [📌 3. Running Tests](#-3-running-tests)
  - [📌 4. Assumptions \& Limitations](#-4-assumptions--limitations)
    - [🔹 Assumptions](#-assumptions)
      - [**🔹 Payment Gateway Architecture: Modular Monolith Over Microservices**](#-payment-gateway-architecture-modular-monolith-over-microservices)
      - [**🔹 Multi-Provider Support \& Provider Switching Behavior**](#-multi-provider-support--provider-switching-behavior)
      - [**🔹 API Authentication: API Key Over JWT**](#-api-authentication-api-key-over-jwt)
      - [**🔹 API Requests Input Validation**](#-api-requests-input-validation)
        - [✅ **Card Number Validation**](#-card-number-validation)
        - [✅ **Expiry Date Validation**](#-expiry-date-validation)
        - [✅ **CVV Validation**](#-cvv-validation)
        - [✅ **Amount Validation**](#-amount-validation)
        - [✅ **Auth Token / Transaction ID Validation**](#-auth-token--transaction-id-validation)
      - [**🔹 Mock Implementation for Authorization (Provider A)**](#-mock-implementation-for-authorization-provider-a)
      - [**🔹 Project Evaluation: Focus on Maintainability \& Scalability**](#-project-evaluation-focus-on-maintainability--scalability)
      - [**🔹 Keeping Capture \& Refund Processes Real-World Relevant**](#-keeping-capture--refund-processes-real-world-relevant)
    - [🔹 Limitations](#-limitations)
  - [📌 5. Additional Documentation](#-5-additional-documentation)


## 📌 **1. Installation & Setup**
### **🔹 Prerequisites**
Before starting, ensure you have:

- **Docker** & **Docker Compose** installed ✅
- PHP **8.2+** installed (for local development) ✅
- Composer installed ✅

### **🔹 Clone the Repository**
```bash
git clone https://github.com/YOUR-REPO/payment-gateway.git
cd payment-gateway
```

### **🔹 Setup and Run the Project**
You can set the project up using two installation methods:

```bash
docker-compose up -d --build
docker exec -it payment_gateway_app bash
composer install
php bin/console c:c
```

## 📌 2. API Endpoints

#### **API Docs**
You can access the API Documentation page with this URL:
```bash
http://localhost:8000/api/doc
```
This way you can view the APIs full documentation about their endpoints, request body, success and failure responses. And of course test them from there.

#### **Postman Pre-configured Collection**
Or, you can use the pre-configured Postman Collection I've prepared for you. \
It can be found on the root directory of this project, filename: **postman-collection.json**

In this collection you will find the endpoints requests ready to be tested, with the **happy path** request bodies.
You just need to import it to Postman, and once the project is up and running, it's ready to go!

#### **Authentication**
All endpoints require a **Bearer Token** (API Key authentication) in the headers, which in this case study plays the role of the Merchant API Key.
Use the **pre-configured token in .env**.
```bash
Authorization: Bearer API_SECRET_KEY
```
#### **Descriptions**

### 🔹 1️⃣ Authorize Payment

Endpoint:
```bash
POST /api/payment/authorize
```
Request Body:
```bash
{
  "card_number": "4111111111111111",
  "expiry_date": "12/25",
  "cvv": "123",
  "amount": 1000
}
```
Success Response (200):
```bash
{
  "status": "success",
  "auth_token": "abcdef123456"
}
```
Failure Response (Invalid Request 400)
```bash
{
  "status": "error",
  "message": [
      "Amount must not be zero."
  ]
}
```
Failure Response (500):
```bash
{
  "status": "error",
  "message": "Authorization failed: Invalid card number for Provider A"
}
```

---

### 🔹 2️⃣ Capture Payment

Endpoint:
```bash
POST /api/payment/capture
```
Request Body:
```bash
{
  "auth_token": "abcdef123456",
  "amount": 1000
}
```
Success Response (200):
```bash
{
  "status": "success",
  "transaction_id": "tx123456789"
}
```
Failure Response (Invalid Request 400)
```bash
{
  "status": "error",
  "message": [
      "Amount must not be zero."
  ]
}
```
Failure Response (500):
```bash
{
  "status": "error",
  "message": "Capture system error, please try again later."
}
```

---

### 🔹 3️⃣ Refund Payment

Endpoint:
```bash
POST /api/payment/refund
```
Request Body:
```bash
{
  "transaction_id": "tx123456789",
  "amount": 1000
}
```
Success Response (200):
```bash
{
  "status": "success",
  "refund_id": "rf123456789"
}
```
Failure Response (Invalid Request 400)
```bash
{
  "status": "error",
  "message": [
      "Amount must not be zero."
  ]
}
```
Failure Response (500):
```bash
{
  "status": "error",
  "message": "Refund system error, please try again later."
}
```

---

## 📌 3. Running Tests
You can run the full PHPUnit test suite using two installation methods:

```bash
docker exec -it payment_gateway_app bash -c "vendor/bin/phpunit --testdox"

OR

docker exec -it payment_gateway_app bash
vendor/bin/phpunit --testdox
```

It should give you the following results:
```bash

Testing
Authorization (App\Tests\Service\Authorization)
 ✔ Authorize payment success
 ✔ Authorize payment failure provider a
 ✔ Successful authorization is logged in redis
 ✔ Failed authorization is logged in redis
 ✔ Authorize payment invalid input

Capture (App\Tests\Service\Capture)
 ✔ Capture payment success
 ✔ Capture payment idempotency
 ✔ Capture without authorization
 ✔ Capture success is logged in redis
 ✔ Capture failure is logged in redis
 ✔ Capture input validation
 ✔ Capture provider failure

Provider A: 600 times
Provider B: 400 times

Load Balancer (App\Tests\Service\Payment\LoadBalancer)
 ✔ Load balancer distribution

Refund (App\Tests\Service\Refund)
 ✔ Refund payment success
 ✔ Refund payment idempotency
 ✔ Refund without capture
 ✔ Refund success is logged in redis
 ✔ Refund failure is logged in redis
 ✔ Refund input validation
 ✔ Refund provider failure

Time: 00:01.037, Memory: 10.00 MB

OK (20 tests, 1253 assertions)

```

## 📌 4. Assumptions & Limitations

### 🔹 Assumptions

#### **🔹 Payment Gateway Architecture: Modular Monolith Over Microservices**  

For this type of solution, I considered two possible architectures:  
1️⃣ **Microservices architecture**  
2️⃣ **Modular monolith**  

I assumed that a **modular monolith** was the right choice for the following reasons:  
✅ The case study explicitly asked for a **simplified** payment gateway, meaning a fully distributed microservices setup would be unnecessary overhead.  
✅ During my previous interview with **Tanmay**, I learned that **Vestiaire Collective's architecture is also a modular monolith**, making it more relevant to follow a similar approach.  
✅ **Scalability is still achievable** within a monolith if services are properly structured with **clear separation of concerns**.  

I implemented a **modular monolith**, ensuring clear service separation between:  
- **Controller Layer:** Handles API requests & responses.  
- **Service Layer:** Encapsulates business logic.  
- **Repository Layer:** Manages data persistence (Redis in this case).  
- **Provider Layer:** Abstracts different payment provider implementations.  

---

#### **🔹 Multi-Provider Support & Provider Switching Behavior**  

The case study mentioned:  
> "Provide a way to switch between them based on a configuration setting."  

I assumed this meant implementing a **configuration-based provider switching mechanism** in the **project’s environment variables**. Based on this setting:  

🔹 **If provider switching is enabled:**  
- When a **card number that doesn't start with "4"** is sent, and the **Load Balancer initially directs it to Provider A**, it would **fail authorization**.  
- Instead of allowing failure, the **provider switcher overrides the decision and reroutes the request to Provider B** to attempt authorization again.  

🔹 **If provider switching is disabled:**  
- The system follows **strict Load Balancer logic**, meaning **all requests go to the assigned provider without rerouting** (failed authorizations are not switched).  

This approach ensures **more controlled provider failover while respecting the load-balancing rules.**  

---

#### **🔹 API Authentication: API Key Over JWT**  

In real-world payment gateways (e.g., Stripe, Adyen, PayPal), authentication is **merchant-based** using **pre-generated API keys** instead of **dynamic JWT authentication**.  

🔹 **Assumption:** API authentication should follow **an API-key-like approach** similar to industry standards.  
🔹 **Implementation:** I simulated this mechanism using **Symfony Security** with a **pre-generated API key stored in `.env`**.  

---

#### **🔹 API Requests Input Validation**  

I researched **best practices for validating payment details** and implemented **strict validation rules** to prevent incorrect or fraudulent data.  

##### ✅ **Card Number Validation**  
✔️ Must be **a string**, **not null**, and **not blank**.  
✔️ Must be **between 13 and 19 characters long** (most cards use 16). \
✔️ Must start with **3, 4, 5, or 6** (aligning with major card networks):  
   - **3 → American Express**  
   - **4 → Visa**  
   - **5 → MasterCard**  
   - **6 → Discover Card** 

##### ✅ **Expiry Date Validation**  
✔️ Must be **a string**, **not null**, and **not blank**.  
✔️ The **card remains valid until the last day of the month mentioned in the expiry date** (e.g., `"02/25"` means the card is valid until **February 28, 2025**).  

##### ✅ **CVV Validation**  
✔️ Must be **a string**, **not null**, and **not blank**.  
✔️ Must be **3 or 4 digits long** (depending on the card provider).  

##### ✅ **Amount Validation**  
✔️ Must be **an integer**, **not null**, and **greater than zero**.  

##### ✅ **Auth Token / Transaction ID Validation**  
✔️ Must be **a string**, **not null**, and **not blank**.  

---

#### **🔹 Mock Implementation for Authorization (Provider A)**  

The case study specified:  
> "Card numbers starting with **4** will be authorized successfully."  

🔹 **Assumption:** The rule should apply to **all** non-`4`-starting card numbers.  
🔹 **Implementation:** I failed authorization for **all card numbers starting with 3, 5, or 6**, ensuring that only **Visa-like cards (starting with 4) are authorized by Provider A**.  

---

#### **🔹 Project Evaluation: Focus on Maintainability & Scalability**  

The case study explicitly mentioned:  
> "Implement logging as you feel it will be useful."  

🔹 **Assumption:** The project will be evaluated **not just on functionality, but also on maintainability and scalability**.  
🔹 **Implementation:** I integrated **structured logging using Monolog**, ensuring **every critical API request, error, and transaction is logged** for better traceability.  

---

#### **🔹 Keeping Capture & Refund Processes Real-World Relevant**  

The case study explicitly mentioned:  
> "Additionally, the provided request and response examples illustrate the 'happy path' where the transaction succeeds without any errors. For a comprehensive solution, ensure you handle edge cases, failed transactions, and error responses appropriately."  

🔹 **Assumption:** A **realistic payment gateway must account for system failures, network issues, and provider-side errors**, rather than only following the **happy path**.  
🔹 **Implementation:**  
- **Capture & Refund operations** now include a **10% failure rate**, simulating real-world system failures.  
- If a failure occurs, the system **logs the issue and returns an appropriate error response** (e.g., `"Capture system error, please try again later."`).  
- This ensures **resilience** by mimicking **unpredictable provider-side issues** that real-world payment gateways encounter.  

---

### 🔹 Limitations

⚠️ **No database persistence** (transactions are stored in **Redis** for fast lookup).\
⚠️ **No asynchronous processing** (no background job handling, no payment transaction retries).

## 📌 5. Additional Documentation

For a deep dive into:

- System **architecture** & **design decisions**
- **Error handling strategy**
- **Logging implementation**
- **Provider selection logic**

Check the full technical documentation in: `docs/technical_overview.md`