# RLG Bootcamp — guest.relog.gg

Web aplikacija za bootcampove Relog Medije: ekipe (klijenti) i interno osoblje (administrator, bootcamp manager, podrška).
Svetla i tamna tema (prekidač ☀/☾ u headeru, pamti se po uređaju). Čist PHP 8.1+ i MySQL, bez frameworka i bez Composera. Radi na običnom cPanel hostingu.

## Šta radi

**Osoblje**
- Dashboard: bootcampovi koji su trenutno u toku, predstojeći, otvoreni tiketi, zahtevi za dodatnu opremu, upozorenja za inventar
- Bootcampovi: ekipa, hotel, soba (može naknadno), period, status, oprema po stavkama (PC, monitori po Hz, stolovi, stolice…), info za ekipu, interne beleške
- Log dodatne opreme po bootcampu: šta je, koliko komada i od kog do kog dana je bilo u sobi (može da se izmeni, ili „Taken out today“)
- Kalendar po mesecima, boje po hotelu
- Inventar: ukupne količine. Upozorava kad rezervacije ili odobreni dodaci pređu stanje u nekom periodu (i uživo u formi)
- Hoteli, pića (lista iz koje ekipe biraju), nalozi osoblja, podešavanja
- Tiketi: chat sa ekipom, statusi, dodela radniku

**Uloge**

| | Administrator | Bootcamp manager | Podrška | Klijent |
|---|---|---|---|---|
| Pravi/menja bootcampove, ekipe, inventar | ✅ | ✅ | ❌ (samo sobu i status) | ❌ |
| Odobrava dodatnu opremu | ✅ | ✅ | ❌ (označava isporuku) | ❌ |
| Tiketi | ✅ | ✅ | ✅ | samo svoji |
| Nalozi osoblja, podešavanja | ✅ | ❌ | ❌ | ❌ |

**Klijent (jedan nalog po ekipi — menadžer ekipe ili glavni igrač)**
- Vidi svoj bootcamp: hotel, sobu, datume, opremu, info (Wi-Fi itd.) — bez cene
- Bira pića za frižider i traži dopunu (dopuna otvara tiket „Drinks refill“)
- Za svaku stolicu bira ručke: obe ostaju / bez obe / bez leve / bez desne
- Traži dodatnu opremu (monitor, kameru…). Manager odobrava i upisuje od kog do kog dana je u sobi
- Otvara tikete i dopisuje se sa podrškom

## Notifikacije

1. **Web push (PWA)** — sajt se doda na početni ekran telefona i šalje notifikacije kao aplikacija. Svako uključuje na *Profile → Notifications on this device*.
   - Android: Chrome → ⋮ → *Install app*
   - iPhone (iOS 16.4+): Safari → Share → *Add to Home Screen*, pa uključiti notifikacije iz instalirane aplikacije
2. **Telegram** (rezerva za osoblje) — bot šalje u grupu podrške nove tikete, poruke klijenata, zahteve za opremu i podsetnike.
3. **Podsetnici** — tiket bez odgovora osoblja posle 1h → podsetnik svim radnicima, pa na svakih sat vremena dok neko ne odgovori. Od 00:00 do 07:00 nema podsetnika (prva notifikacija o novom tiketu stiže uvek).

Manager i administrator dobijaju sve tikete. Klijent dobija notifikaciju kad podrška odgovori, kad se dodeli soba, kad je soba spremna i kad se odluči o zahtevu za opremu.

## Instalacija na cPanel (guest.relog.gg)

1. **Subdomen** `guest.relog.gg` → document root npr. `/home/USER/guest.relog.gg`.
2. **Baza:** cPanel → *MySQL Databases* → nova baza + korisnik sa ALL PRIVILEGES. Tabele se prave automatski.
3. **FTP nalog** sa korenom u document rootu subdomena. U GitHub repou → *Settings → Secrets and variables → Actions* dodaj:
   `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. Svaki push na `main` automatski deployuje (isto kao veto-tool).
4. **Konfiguracija:** na serveru kopiraj `app/config.sample.php` → `app/config.php` i popuni bazu, email (napravi `no-reply@relog.gg` u *Email Accounts*), Telegram. Deploy nikad ne dira `app/config.php`.
5. Otvori `https://guest.relog.gg/setup` → napravi administratorski nalog (radi samo dok nema nijednog korisnika).
6. **Cron** (cPanel → *Cron Jobs*, svaki minut):
   ```
   * * * * * /usr/local/bin/php /home/USER/guest.relog.gg/cron/run.php >/dev/null 2>&1
   ```
   Ako PHP CLI nije dostupan: postavi `cron_key` u configu i koristi
   `curl -s "https://guest.relog.gg/cron?key=TVOJ_KLJUC" >/dev/null`.
7. **Telegram bot:** @BotFather → `/newbot` → token. Dodaj bota u grupu podrške, pošalji poruku u grupu i otvori
   `https://api.telegram.org/bot<TOKEN>/getUpdates` → `chat.id` (počinje sa `-100…`). Upiši oba u config.
8. U *Setup → Settings* testiraj email, Telegram i cron.

Prvi koraci posle instalacije: *Inventory* (upiši količine i označi šta ekipe smeju da traže kao dodatak) → *Hotels* → *Teams* (pozivnica ide na email klijenta) → *New bootcamp*.

## Struktura

```
index.php            front controller (sve rute)
sw.js                service worker (push)
manifest.webmanifest PWA manifest
app/
  config.sample.php  šablon konfiguracije (config.php nije u gitu)
  routes.php         sve rute
  controllers/       logika stranica
  views/             HTML šabloni
  lib/               baza, auth, notifikacije, web push (VAPID), domen
  vendor/PHPMailer   slanje emaila (SMTP)
cron/run.php         cron (statusi bootcampova, podsetnici, čišćenje)
assets/              CSS, JS, fontovi (Anek Latin), logo
storage/logs/        logovi (mail.log, push.log, telegram.log, php-error.log)
```

Šema baze je u `app/lib/schema.php`. Izmene šeme se dodaju kao nova migracija na kraj liste i izvršavaju se automatski.
