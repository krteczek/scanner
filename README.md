# 📁 Scanner - Lokální analyzátor webových projektů

Jednoduchý lokální nástroj pro skenování a analýzu PHP/JS projektů s možností zobrazení kódu a reportování problémů.

## 🚀 Rychlý start

1. **Umístění**: Nakopírujte složku `scanner` vedle svých projektů (o úroveň výš).

2. **Přístup**: Otevřete v prohlížeči `http://localhost/cesta/scanner/`
3. **Použití**: Klikněte na projekt → zobrazí se report → klikněte na soubor pro detail.

## 📁 Struktura projektu

# 📁 Scanner - Lokální analyzátor webových projektů

Jednoduchý lokální nástroj pro skenování a analýzu PHP/JS projektů s možností zobrazení kódu a reportování problémů.

## 🚀 Rychlý start

1. **Umístění**: Nakopírujte složku `scanner` vedle svých projektů (o úroveň výš).
2. **Přístup**: Otevřete v prohlížeči `http://localhost/cesta/scanner/`
3. **Použití**: Klikněte na projekt → zobrazí se report → klikněte na soubor pro detail.

## 📁 Struktura projektu

```markdown

scanner/
├──index.php              # Hlavní vstupní bod
├──autoloader.php        # Načítání tříd
├──config/
│├── actions.php       # Konfigurace dostupných akcí
│├── app.php           # Hlavní nastavení aplikace
│└── rules.php         # Pravidla pro analýzu kódu
├──src/
│├── Core/            # Jádro aplikace
│├── Handlers/        # Zpracování požadavků
│├── Services/        # Business logika
│└── Utilities/       # Pomocné třídy
├──public/
│└── style.css        # Základní styly
└──templates/           # Šablony (budoucí použití)

```

## 🎯 Dostupné akce (URL parametry)

| Akce | Parametry | Popis |
|------|-----------|-------|
| `?action=list` | - | Seznam všech dostupných projektů |
| `?action=scan` | `&project=nazev` | Skenování konkrétního projektu |
| `?action=view` | `&project=nazev&file=cesta` | Zobrazení obsahu souboru |
| `?action=api_rules` | - | JSON API s pravidly pro analýzu |
| `?action=error` | `&message=text` | Zobrazení chybové stránky |

## 🔧 Konfigurace

### Pravidla analýzy (`config/rules.php`)
Upravte pole `$rules` pro definici vlastních kontrol:
```php
'no_debug_code' => [
    'pattern' => '/\b(var_dump|print_r|dd\(|console\.log)\b/',
    'message' => 'Nalezen debug kód',
    'severity' => 'warning'
]
```

Akce (config/actions.php)

Přidejte nové handlery pro rozšíření funkcionality:

```php
'export' => \Scanner\Handlers\ExportHandler::class,
```

🛠️ Vývoj

Přidání nové akce

1. Vytvořte handler v src/Handlers/NazevHandler.php
2. Zaregistrujte v config/actions.php
3. Handler musí implementovat HandlerInterface::handle()

Styly

Základní styly jsou v public/style.css. Pro mobilní zobrazení použijte media queries.

🔍 Funkce

· Automatické objevování projektů (složky o úroveň výš)
· Analýza PHP/JS kódu podle konfigurovatelných pravidel
· Detailní zobrazení souborů s syntax highlighting
· JSON API pro pravidla
· Responsivní design pro mobilní zařízení
· Jednoduchá architektura pro lokální použití

❌ Omezení

· Pouze pro lokální použití (ne pro produkci)
· Bez autentizace/autorizace
· Limit velikosti zobrazovaných souborů: 2 MB
· Podporované jazyky: PHP, JavaScript, HTML, CSS, JSON

📝 TODO

· Přidat export reportů (CSV/JSON)
· Implementovat pokročilé logování
· Přidat více analyzátorů kódu
· Vylepšit uživatelské rozhraní
· Přidat statistiky projektu

📄 Licence

Lokální nástroj pro vlastní potřebu.

```

---

## 📦 **Kompletní seznam vygenerovaných souborů:**

1. ✅ `config/actions.php` - Registr akcí
2. ✅ `src/Handlers/HandlerInterface.php` - Rozhraní
3. ✅ `src/Handlers/ProjectListHandler.php` - Seznam projektů  
4. ✅ `src/Handlers/ProjectScanHandler.php` - Skenování projektu
5. ✅ `src/Handlers/FileViewHandler.php` - Zobrazení souborů
6. ✅ `src/Handlers/RulesApiHandler.php` - API pravidel
7. ✅ `src/Handlers/ErrorHandler.php` - Zpracování chyb
8. ✅ `index.php` - Hlavní vstupní bod (aktualizovaný)
9. ✅ `autoloader.php` - Autoloader (aktualizovaný)
10. ✅ `README.md` - Dokumentace
