V aktuální verzi projektu nice‑scripting‑solution jste odstranil několik dřívějších chyb, ale klíčová architektonická slabina – spouštění cizího JavaScriptu přes V8Js přímo v PHP workeru – zůstává. Níže shrnuji stav, zlepšení, přetrvávající rizika a co by bylo vhodné ještě doplnit či na pohovoru vysvětlit.

1) Co se skutečně změnilo

UI - Blade formuláře nyní inicializují CodeMirror 5 (monokai, lint) namísto čisté <textarea> - + výrazně lepší DX, ale bez IntelliSense či diffu verzí
Konfigurace - Všechny limity (timeout, RAM, regex blacklist …) sjednoceny v config/scripting.php - + přehledné, 12‑factor‑ready
ACL - Přibyl balíček spatie/laravel‑permission a policy ScriptPolicy - + dobrý základ pro RBAC
Testy - K projektu jsou tři PHPUnit testy; běží lokálně, ale žádná CI pipeline ani Dockerfile tu není - ± pokrok, ale bez automatické kontroly

Poznámka: v archivu chybí deklarovaný ResourceMonitorService, .github/workflows/* i Docker kontejner. Proto některé dříve deklarované funkce pořád neběží.

2) Hlavní slabá místa

2.1 V8Js uvnitř PHP procesu
- V8Js sdílí paměť a CPU se zbytkem aplikace. Nekonečná smyčka (while(true){}) nebo masivní alokace mohou worker shodit navzdory soft‑limitům
- PECL modul má dlouhé prodlevy při podpoře nových verzí PHP – přechod na 8.4/8.5 může být zablokovaný týdny

2.2 Bez OS‑level sandboxu
- V repozitáři není Dockerfile ani cgroups limity. Bez --memory / --cpus může jeden skript vyčerpat celý pod

2.3 Regex‑ová validace kódu
- ScriptSecurityService používá jen regulární výrazy; robustnější AST analýza nebo byte‑code whitelist chybí, takže sofistikovanější payload projde.

2.4 Chybějící verze & rollback
- DB sice ukládá skripty, ale UI ani API neumí diff a vratné verze; hrozí, že chybná úprava hned zasáhne produkci.

2.5 Observabilita
- Nenašel jsem Prometheus/Loki integraci ani Laravel Horizon dashboard; bez metrik je obtížné včas poznat runaway skript

3) Otázky, které je ještě potřeba dovyjasnit
- Runtime isolace – zůstane V8Js v PHP, nebo plánujete side‑car (Deno/Wasm)?
Deno má výchozí „deny‑all“ permission model a granulární flagy --allow‑net, --allow‑read
- Kvóty & billing – měříme CPU‑sekundy / RAM podle klienta?
- Revize & CI gate – projde každý skript statickou analýzou + testy, nebo může klient rovnou deploynout?
- Secret management – kde skripty berou API tóny?
- Budoucí jazyky – počítá se s Python/Lua přes Wasm, nebo zůstane jen JS?

4) Doporučené další kroky
- 	Oddělit runtime do side‑car (např. Deno nebo Wasm) a nastavit cgroups limity (přínos: Tvrdé omezení CPU/RAM, eliminace RCE v FPM)
- 	Přidat CI workflow (build, test, Larastan) a Dockerfile (přínos: Automatická kontrola kvality & repro­dukovatelné deploye)
- 	Implementovat AST validator místo regexů (přínos: Spolehlivější zachycení škodlivých patternů)
- 	V UI přejít z CodeMirror na Monaco (plné IntelliSense) nebo aspoň diff & rollback (přínos: Lepší DX, méně chyb)
- Zapojit Prometheus + Grafana pro skriptové metriky (přínos: Rychlejší reakce na runaway skripty)

5) Shrnutí

- Projekt je dnes funkční MVP – má centralizovanou konfiguraci, práva přes spatie/laravel‑permission, CodeMirror editor a základní testy. Pro reálný provoz ale chybí tvrdá izolace runtime, OS‑limity, CI/CD pipeline a verzování skriptů.