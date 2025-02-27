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
  - [🚀 **1. Installation \& Setup**](#-1-installation--setup)
    - [**🔹 Prerequisites**](#-prerequisites)
    - [**🔹 Clone the Repository**](#-clone-the-repository)
    - [**🔹 Setup and Run the Project**](#-setup-and-run-the-project)
  - [📌 2. API Endpoints](#-2-api-endpoints)
      - [**OpenAPI Docs**](#openapi-docs)
      - [**Postman Pre-configured Collection**](#postman-pre-configured-collection)
      - [**APIs Security**](#apis-security)
  - [📌 3. Running Tests](#-3-running-tests)
  - [📌 4. Assumptions \& Limitations](#-4-assumptions--limitations)
    - [🔹 Assumptions](#-assumptions)
    - [🔹 Limitations](#-limitations)
  - [📌 5. Additional Documentation](#-5-additional-documentation)


## 🚀 **1. Installation & Setup**
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
```

## 📌 2. API Endpoints

#### **OpenAPI Docs**
You can access the API OpenAPI Documentation page with this URL:
```bash
http://localhost:8000/api/doc
```
This way you can view the APIs full documentation about their endpoints, request body, success and failure responses. And of course test them from there.

#### **Postman Pre-configured Collection**
Or, you can use the pre-configured Postman Collection I've prepared for you. \
It can be found on the root directory of this project, filename: **postman-collection.json**

In this collection you will find the 3 endpoints requests ready to be tested, with the **happy path** request bodies.
- `POST /api/payment/authorize`
- `POST /api/payment/capture`
- `POST /api/payment/refund`

You just need to import it to Postman, and once the project is up and running, it's ready to go!

#### **APIs Security**
All endpoints require a **Bearer Token** (API Key authentication) in the headers, which in this case study plays the role of the Merchant API Key
```bash
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3NDA1OTA1NDYsImV4cCI6MTc0MDU5NDE0Niwicm9sZXMiOlsiUk9MRV9NRVJDSEFOVCJdLCJ1c2VybmFtZSI6IlZlc3RpYWlyZSBDb2xsZWN0aXZlIn0.JqJY9RFyVQtBzSf_gBEd2s-m_Pm3zAi64M4VciR0JvxhbwKm_N0BO9VetwuS-Bo0pM4PoG5mPpiduK7faYoSo6RezYQ99wPSUz-6Br8DpOHU_17QbrEVvaoL7VkThnN0VdVxKvLdyv2ityeuxpxXwu9inxqM7JwVcP2b-8wasBH5MVJKm4hLTOIo4ti6Iys5DbeCUEjmFnS36i8s-Tub-MYl2BNkruBgdMV0_DM9R9n3mBflrfbNRmCie2HktbbIdtiGosI22aH5OAXgYDvJk0jgnINkBBOhFH2jGhEDyPlJjR-ISoIHKsCWeOXNU88V091W5g49TUWw2ew0K4s_Lg
```

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