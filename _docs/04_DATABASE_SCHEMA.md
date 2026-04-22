# DRUKARNIA ERP — DATABASE SCHEMA OVERVIEW

> **Dla:** Developers, Architects  
> **Zawiera:** Entity Relationship Diagram, key tables, migrations checklist  
> **Format:** Pseudo-SQL + markdown (real migrations in `database/migrations/`)

---

## ERD — Entity Relationship Diagram

```
USERS & AUTH
  users (id, email, password, imie, nazwisko, aktywny, klient_id, last_login_at, deleted_at)
  ├→ roles (id, nazwa, guard_name)
  └→ activity_log (id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties)

CLIENTS (CRM)
  klienci (id, typ B2B/B2C, imie_nazwa, nip, regon, email, telefon, status, poziom_lojalnosci_id)
  ├→ osoby_kontaktowe (id, klient_id, imie, email, telefon)
  ├→ tagi (id, nazwa) ──→ klient_tag (pivot)
  └→ poziomy_lojalnosci (id, nazwa, prog_obrotow, rabat_procent)

ORDERS (Core)
  zamowienia (id, numer DRK-YYYY-XXXXX, klient_id, menedzer_id, status, priorytet, channel)
  ├→ pozycje_zamowienia (id, zamowienie_id, nazwa, naklad, format, material, kolorystyka, cena_netto)
  ├→ specyfikacje_druku (id, pozycja_id, parametry JSONB)
  ├→ historia_statusow (id, zamowienie_id, old→new status, user_id, komentarz)
  │
  ├→ [PRICING]
  │  ├→ kalkulacje_cen (id, zamowienie_id, breakdown JSONB, cena_brutto)
  │  └→ koszty_wlasne (id, zamowienie_id, material_koszt, marza_realna)
  │
  ├→ [FILES / DAMS]
  │  ├→ pliki (id, nazwa_oryg, slug, mime, rozmiar, s3_key, checksum)
  │  ├→ wersje_plikow (id, plik_id, numer_wersji, s3_key, aktywna)
  │  └→ powiazania_plikow (id, plik_id, fileable_type, fileable_id, rola)
  │
  ├→ [APPROVAL]
  │  ├→ zadania_akceptacji (id, zamowienie_id, plik_id, status, link_token, runda_nr, wygasa_at)
  │  └→ komentarze_akceptacji (id, zadanie_id, tresc, pozycja_x, pozycja_y, strona)
  │
  ├→ [PRODUCTION]
  │  ├→ zlecenia_produkcyjne (id, zamowienie_id, status, priorytet, data_planowana)
  │  └→ etapy_produkcji (id, zlecenie_id, typ, status, maszyna_id, operator_id, czas_start, czas_stop)
  │
  ├→ [SHIPPING]
  │  ├→ wysylki (id, zamowienie_id, kurier, numer_listu, tracking_url, koszt)
  │  └→ adresy_wysylki (id, wysylka_id, imie_nazwisko, ulica, kod_pocztowy)
  │
  ├→ [COMPLAINTS]
  │  ├→ reklamacje (id, zamowienie_id, klient_id, powod, status, decyzja, rozpatrujacy_id)
  │  └→ dowody_reklamacji (id, reklamacja_id, plik_id, typ)
  │
  └→ [FINANCE]
     ├→ dokumenty_finansowe (id, zamowienie_id, typ proforma/fz/fv/korekta, numer_subiekt, kwota_netto, status)
     └→ platnosci (id, dokument_id, kwota, metoda, transaction_id_provider)

COMMUNICATION
  watki_komunikacji (id, zamowienie_id nullable, klient_id nullable, temat, status)
  ├→ wiadomosci (id, watek_id, kierunek, kanal email/sms/system, tresc, zewnetrzny_id UNIQUE)
  ├→ zalaczniki_wiadomosci (id, wiadomosc_id, plik_id)
  ├→ szablony_wiadomosci (id, nazwa, kanal, temat, tresc_template, zmienne JSON)
  └→ powiadomienia (id, user_id, typ, tytul, tresc, link, przeczytane)

PRODUCTION
  maszyny (id, nazwa, typ, status, koszt_godz)
  └→ operatorzy_produkcji (id, user_id, specjalizacja JSON, dostepny)

SHOP & PORTAL (Future F3)
  produkty (id, slug, nazwa, opis, kategoria, szablon JSONB, aktywny)
  ├→ projekty_klientow (id, klient_id nullable, plik_id, state JSON versioned, active_version)
  ├→ koszyk (id, user_id nullable, session_id, status pending/completed)
  └→ pozycje_koszyka (id, koszyk_id, produkt_id, quantity, cena_na_sztuke)
```

---

## KEY TABLES (Detailed)

### `users`

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    imie VARCHAR(100),
    nazwisko VARCHAR(100),
    aktywny BOOLEAN DEFAULT TRUE,
    klient_id BIGINT REFERENCES klienci(id),
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,                    -- soft delete
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### `zamowienia`

```sql
CREATE TABLE zamowienia (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    numer VARCHAR(20) UNIQUE NOT NULL,           -- DRK-2026-00001
    klient_id BIGINT REFERENCES klienci(id),
    menedzer_id BIGINT REFERENCES users(id),
    status VARCHAR(50) NOT NULL,                  -- FSM state
    priorytet ENUM('normal', 'wysoki', 'pilny'),
    channel ENUM('email', 'phone', 'manual', 'shop_guest', 'shop_user', 'portal_repeat', 'api'),
    termin_realizacji DATE,
    parent_order_id BIGINT REFERENCES zamowienia(id),  -- dla dodruku
    source_email VARCHAR(255),                    -- jeśli channel=email
    uwagi_wewnetrzne TEXT,
    uwagi_klienta TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (menedzer_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_klient_id (klient_id),
    INDEX idx_created_at (created_at)
);
```

### `klienci`

```sql
CREATE TABLE klienci (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    typ ENUM('B2B', 'B2C') NOT NULL,
    imie_nazwa VARCHAR(255) NOT NULL,
    nip VARCHAR(10),                              -- for B2B
    regon VARCHAR(9),
    email_glowny VARCHAR(255),
    telefon_glowny VARCHAR(20),
    adres_ulica VARCHAR(255),
    adres_miasto VARCHAR(100),
    adres_kod VARCHAR(10),
    adres_kraj VARCHAR(2) DEFAULT 'PL',
    status ENUM('aktywny', 'vip', 'zablokowany') DEFAULT 'aktywny',
    poziom_lojalnosci_id BIGINT REFERENCES poziomy_lojalnosci(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE INDEX idx_nip (nip),
    UNIQUE INDEX idx_email_glowny (email_glowny)
);
```

### `kalkulacje_cen`

```sql
CREATE TABLE kalkulacje_cen (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    zamowienie_id BIGINT REFERENCES zamowienia(id),
    pozycja_id BIGINT REFERENCES pozycje_zamowienia(id),
    
    cena_bazowa DECIMAL(12, 2),
    modyfikatory JSON,                            -- Array of { label, value, type }
    rabat_kwota DECIMAL(12, 2),
    cena_netto DECIMAL(12, 2) GENERATED ALWAYS AS (cena_bazowa - rabat_kwota) STORED,
    stawka_vat INT DEFAULT 23,                    -- %
    vat_kwota DECIMAL(12, 2) GENERATED ALWAYS AS (ROUND(cena_netto * stawka_vat / 100, 2)) STORED,
    cena_brutto DECIMAL(12, 2) GENERATED ALWAYS AS (cena_netto + vat_kwota) STORED,
    
    zaakceptowana_przez_id BIGINT REFERENCES users(id),
    zaakceptowana_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    
    FOREIGN KEY (zamowienie_id) REFERENCES zamowienia(id),
    FOREIGN KEY (pozycja_id) REFERENCES pozycje_zamowienia(id)
);
```

### `pliki`

```sql
CREATE TABLE pliki (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    nazwa_oryginalna VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    mime_type VARCHAR(50),
    rozmiar_bajtow BIGINT,
    sciezka_s3 VARCHAR(255) UNIQUE,               -- MinIO key
    bucket VARCHAR(50) DEFAULT 'drukarnia-files',
    
    thumbnail_sm_url VARCHAR(500),                -- MinIO presigned URL (150px)
    thumbnail_md_url VARCHAR(500),                -- (400px)
    thumbnail_lg_url VARCHAR(500),                -- (1200px)
    
    uploadowany_przez_id BIGINT REFERENCES users(id),
    checksum_sha256 VARCHAR(64) UNIQUE,
    
    deleted_at TIMESTAMP NULL,                    -- soft delete
    created_at TIMESTAMP,
    
    INDEX idx_checksum (checksum_sha256),
    INDEX idx_s3_key (sciezka_s3)
);
```

### `wersje_plikow`

```sql
CREATE TABLE wersje_plikow (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    plik_id BIGINT REFERENCES pliki(id),
    numer_wersji INT,
    sciezka_s3 VARCHAR(255),                      -- MinIO key for this version
    komentarz TEXT,
    uploadowany_przez_id BIGINT REFERENCES users(id),
    aktywna BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    
    UNIQUE INDEX idx_plik_wersja (plik_id, numer_wersji)
);
```

### `powiazania_plikow` (Polymorphic)

```sql
CREATE TABLE powiazania_plikow (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    plik_id BIGINT REFERENCES pliki(id),
    
    fileable_type VARCHAR(100),                   -- 'Zamowienie', 'Wiadomosc', 'ZadanieAkceptacji', etc.
    fileable_id BIGINT,
    
    rola VARCHAR(50),                             -- 'artwork', 'dokument', 'dowod', 'zdjecie'
    created_at TIMESTAMP,
    
    UNIQUE INDEX idx_polymorphic (plik_id, fileable_type, fileable_id, rola),
    INDEX idx_fileable (fileable_type, fileable_id)
);
```

### `zadania_akceptacji`

```sql
CREATE TABLE zadania_akceptacji (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    zamowienie_id BIGINT REFERENCES zamowienia(id),
    plik_wersja_id BIGINT REFERENCES wersje_plikow(id),
    
    status ENUM('oczekuje', 'zaakceptowane', 'do_poprawy', 'odrzucone', 'wygasle'),
    link_token VARCHAR(36) UNIQUE,                -- UUID
    runda_nr INT DEFAULT 1,
    
    wyslane_przez_id BIGINT REFERENCES users(id),
    wyslane_at TIMESTAMP,
    odpowiedz_at TIMESTAMP NULL,
    wygasa_at TIMESTAMP,                          -- 7 days from wyslane_at
    
    komentarz_klienta TEXT,
    created_at TIMESTAMP,
    
    INDEX idx_token (link_token),
    INDEX idx_zamowienie (zamowienie_id),
    INDEX idx_wygasa_at (wygasa_at)
);
```

### `zlecenia_produkcyjne`

```sql
CREATE TABLE zlecenia_produkcyjne (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    zamowienie_id BIGINT REFERENCES zamowienia(id) UNIQUE,
    status ENUM('oczekuje', 'w_trakcie', 'wstrzymane', 'gotowe'),
    priorytet ENUM('normal', 'wysoki', 'pilny'),
    data_planowana DATE,
    notatki TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### `etapy_produkcji`

```sql
CREATE TABLE etapy_produkcji (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    zlecenie_id BIGINT REFERENCES zlecenia_produkcyjne(id),
    
    typ ENUM('prepress', 'druk', 'postpass_ciecie', 'postpass_laminat', 'postpass_inne'),
    kolejnosc INT,
    status ENUM('oczekuje', 'w_trakcie', 'gotowe', 'pominiety'),
    
    maszyna_id BIGINT REFERENCES maszyny(id),
    operator_id BIGINT REFERENCES users(id),
    
    czas_start TIMESTAMP NULL,
    czas_stop TIMESTAMP NULL,
    czas_normatywny_min INT,                      -- estimated time
    
    notatki_operatora TEXT,
    created_at TIMESTAMP,
    
    INDEX idx_zlecenie (zlecenie_id),
    INDEX idx_status (status)
);
```

### `dokumenty_finansowe`

```sql
CREATE TABLE dokumenty_finansowe (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    zamowienie_id BIGINT REFERENCES zamowienia(id),
    
    typ ENUM('proforma', 'zaliczkowa', 'vat', 'paragon', 'korekta'),
    numer_subiekt VARCHAR(50),                    -- for tracking
    
    kwota_netto DECIMAL(12, 2),
    stawka_vat INT DEFAULT 23,
    kwota_vat DECIMAL(12, 2),
    kwota_brutto DECIMAL(12, 2),
    
    status ENUM('wystawiony', 'oplacony', 'przeterminowany'),
    
    link_platnosci VARCHAR(500),                  -- Payment gateway URL (Proforma)
    mpp_required BOOLEAN DEFAULT FALSE,           -- >15k PLN B2B
    mpp_status ENUM('pending', 'sent', 'accepted', 'rejected'),
    
    wystawiona_at TIMESTAMP,
    oplacona_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    
    INDEX idx_zamowienie (zamowienie_id),
    INDEX idx_typ (typ),
    INDEX idx_status (status)
);
```

### `wysylki`

```sql
CREATE TABLE wysylki (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    zamowienie_id BIGINT REFERENCES zamowienia(id),
    
    metoda ENUM('inpost_paczkomat', 'dpd', 'dhl', 'gls', 'odbior_osobisty'),
    status ENUM('przygotowywana', 'wyslana', 'w_transporcie', 'dostarczona', 'nieudana', 'zwrot'),
    
    numer_listu VARCHAR(100),
    tracking_url VARCHAR(500),
    etykieta_url VARCHAR(500),                    -- MinIO URL
    
    koszt_wysylki_netto DECIMAL(10, 2),
    
    data_nadania DATE,
    data_dostawy DATE,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (zamowienie_id) REFERENCES zamowienia(id),
    INDEX idx_zamowienie (zamowienie_id)
);
```

### `reklamacje`

```sql
CREATE TABLE reklamacje (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    zamowienie_id BIGINT REFERENCES zamowienia(id),
    klient_id BIGINT REFERENCES klienci(id),
    
    powod TEXT,                                   -- "Kolory niezgodne", "Opóźnienie", etc.
    opis TEXT,
    
    status ENUM('zgloszona', 'w_rozpatrzeniu', 'uznana', 'odrzucona', 'dodruk', 'zwrot'),
    decyzja TEXT,
    
    rozpatrujacy_id BIGINT REFERENCES users(id),
    rozpatrzona_at TIMESTAMP NULL,
    
    created_at TIMESTAMP,
    
    FOREIGN KEY (zamowienie_id) REFERENCES zamowienia(id),
    FOREIGN KEY (klient_id) REFERENCES klienci(id)
);
```

### `activity_log`

```sql
CREATE TABLE activity_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    log_name VARCHAR(255) NULL,
    description TEXT NOT NULL,
    subject_type VARCHAR(255) NULL,
    subject_id BIGINT NULL,
    causer_type VARCHAR(255) NULL,
    causer_id BIGINT NULL,
    properties JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_activity_log_log_name (log_name),
    INDEX idx_activity_log_subject (subject_type, subject_id),
    INDEX idx_activity_log_causer (causer_type, causer_id)
);
```

---

## INDEKSY I PERFORMANCE

### Critical Indexes

```sql
-- Lookups
CREATE INDEX idx_zamowienia_status ON zamowienia(status);
CREATE INDEX idx_zamowienia_klient ON zamowienia(klient_id);
CREATE INDEX idx_wiadomosci_externos_id ON wiadomosci(zewnetrzny_message_id);

-- Time-based (for archival, partitioning)
CREATE INDEX idx_activity_log_log_name ON activity_log(log_name);

-- Partial (for hot queries)
CREATE INDEX idx_orders_waiting_payment ON zamowienia(id) WHERE status = 'WAITING_PAYMENT';
CREATE INDEX idx_production_in_progress ON etapy_produkcji(id) WHERE status = 'w_trakcie';
```

### Query Optimization

```sql
-- Don't:
SELECT * FROM zamowienia WHERE status = 'WAITING_PAYMENT';

-- Do:
SELECT id, numer, klient_id, menedzer_id, created_at FROM zamowienia 
WHERE status = 'WAITING_PAYMENT'
ORDER BY created_at DESC
LIMIT 100;
```

---

## MIGRACJE — CHECKLIST

### Faza 1 (F1)

- [x] Users, Roles, Permissions (Spatie)
- [x] Paczka A: CRM Core (Klienci, Osoby kontaktowe, Tagi)
- [x] Paczka B: Orders & Specs (Zamówienia, Pozycje, Specyfikacje)
- [x] Paczka C: Pricing Engine (Reguły, Kalkulacje, Koszty)
- [x] Paczka D: DAMS & Production (Pliki, Wersje, Zlecenia, Maszyny)
- [x] Communication Hub (Wątki, Wiadomości, Powiadomienia)
- [x] Status Definitions & Audit Logs (Spatie Activitylog)

### Faza 2 (F2)

- [ ] 2026_02_01 — Preflight Reports
- [ ] 2026_02_02 — Approval Tasks
- [ ] 2026_02_03 — Shipping
- [ ] 2026_02_04 — Complaints & Reprints

### Faza 3 (F3)

- [ ] 2026_03_01 — Finance (Documents, Payments)
- [ ] 2026_03_02 — Shop (Products, Cart, Orders)
- [ ] 2026_03_03 — Design Projects

---

## SQL EXAMPLES

### Find slow orders (still in WAITING_PAYMENT after 7 days)

```sql
SELECT o.id, o.numer, o.klient_id, o.created_at, DATEDIFF(NOW(), o.created_at) as days_waiting
FROM zamowienia o
WHERE o.status = 'WAITING_PAYMENT'
  AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY o.created_at ASC;
```

### Calculate average delivery time per client

```sql
SELECT 
    k.id,
    k.imie_nazwa,
    COUNT(z.id) as orders_count,
    AVG(DATEDIFF(w.data_dostawy, z.created_at)) as avg_days_to_delivery
FROM klienci k
JOIN zamowienia z ON z.klient_id = k.id
LEFT JOIN wysylki w ON w.zamowienie_id = z.id
WHERE z.status = 'COMPLETED'
GROUP BY k.id
ORDER BY avg_days_to_delivery DESC;
```

### Top 10 most profitable products

```sql
SELECT 
    pz.nazwa_produktu,
    COUNT(*) as orders,
    AVG(kc.cena_brutto) as avg_price,
    SUM(kw.marza_realna) as total_profit
FROM pozycje_zamowienia pz
JOIN kalkulacje_cen kc ON kc.pozycja_id = pz.id
JOIN koszty_wlasne kw ON kw.zamowienie_id = pz.zamowienie_id
GROUP BY pz.nazwa_produktu
ORDER BY total_profit DESC
LIMIT 10;
```

---

**Dokument:** DATABASE_SCHEMA.md  
**Wersja:** 1.0 (F1)  
**Ostatnia aktualizacja:** 2026-04-20

