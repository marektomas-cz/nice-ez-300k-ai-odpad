| Požadavek                     | Jak je pokryt | Poznámka                                                                                                        |

\| ----------------------------- | ------------- | --------------------------------------------------------------------------------------------------------------- |
| Izolované, bezpečné prostředí | \*\*Částečně\*\*  | V8Js sandbox + limity; chybí OS izolace, chroot/cgroups, stále jeden PHP proces.                                |
| Volba jazyka                  | \*\*JS\*\* (V8Js) | Snadná adopce, ale rozšíření má malý maintainer‑pool a dlouhé release cykly ([PECL][1], [GitHub][2]).           |
| Sandbox & resource‑limits     | \*\*Částečné\*\*  | Blacklist + V8Js limity; open\\_basedir nepoužito (a stejně není dostatečný) ([Bencteux][3], [Server Fault][4]). |
| API k datům                   | \*\*Ano\*\*       | `ScriptingApiService` injektuje Database/HTTP/Event/Logging/Validation API.                                     |
| UI editor, lint, debug        | \*\*Ne\*\*        | Blade textarea; bez syntax highlight, lintu, verzování.                                                         |
| Spouštění (event/cron/ručně)  | \*\*Ano\*\*       | Listener + plánovaný Artisan command.                                                                           |
| Monitoring výkonu             | \*\*Částečné\*\*  | Služba deklarována, ale není napojen konkrétní watchdog/cgroups.                                                |
| Rozšiřitelnost/verzování      | \*\*Základ\*\*    | Strukturou služeb se to dá rozšiřovat, chybí migrační strategie skriptů.                                        |


1) Kvalita kódu a údržba
Chybějící soubory – ResourceMonitorService je importován v několika třídách i testech, ale nikde neexistuje ⇒ projekt aktuálně neproběhne composer test.
Testy – jsou zde základní Feature a Unit testy, avšak závisí na neexistujících třídách; CI konfigurace nenajdete.
Composer balíčky – přítomen v8js/v8js, ale reálně se instaluje jako PECL rozšíření, nikoliv přes Composer; build na CI/production serveru není popsaný.
Duplicitní logika – regex blacklisty vs. wrapScriptCode; lepší by bylo jeden centralizovaný AST validator.
Security debt – ochrana se spoléhá na „blokování eval“ a limity V8Js. Žádná detekce while(true){} smyček, žádné per‑tenant cgroups.

2) Hlavní rizika

RCE/DoS uvnitř PHP procesu – pokud vlastní skript obejde limity, shodí celý worker a může číst env proměnné. V8Js sice umí nastavit čas/paměť, ale není to plnohodnotný sandbox
Údržba V8Js – projekt má malé community; při PHP 8.4+ nemusí být binárky dostupné včas
Slabé UI – textarea s JS bez lintu vede ke špatné DX a vysoké chybovosti.
Nekompletní monitoring – bez opravdového watchdogu lze limity obejít (new Array(1e9).fill(0)), PHP proces padne.
open\_basedir iluze – i kdyby bylo nastaveno, není to spolehlivá sandbox technologie

3) Doporučené změny

3.1 Přesun runtime mimo PHP

Použijte Deno nebo jiný JS runtime jako side‑car: deno run --allow-net --allow-env=DB\_URL ... – permission model je granulární a výchozí je deny all
PHP bude jen orchestrátor (queue job → gRPC/REST volání); tím minimalizujete rizika RCE v FPM.

3.2 Sandbox / OS limity

Kontejner (Docker) + cgroups pro CPU/RAM + ulimit pro file‑descriptors.
Watchdog proces (např. via systemd scope) znemožní nekonečné smyčky i fork‑bomby.

3.3 Lepší DX

Editor Monaco v SPA (React/Vue + Inertia/Livewire) s lintem, autocompletem a intellisense.
Preview běhu, verzování skriptů, diff mezi verzemi.

3.4 Bezpečnostní vrstva

Místo regexů použijte static AST analýzu (Acorn/Esprima) a whitelist povolených node typů.
Vkládejte podpisy & checksums skriptů, ukládejte každou verzi.

3.5 CI/CD a testy

Doplnit GitHub Actions (lint, PHPUnit, Larastan, Pest).
Vyřešit chybějící třídy a doplnit integrační testy na běh skriptu v sandboxu.

3.6 Alternativní sandbox v PHP

Pokud zůstane jediný proces, zvažte na úrovni PHP balíček typu spatie/php‑sandbox a zpevněte FS přístup přes chroot a SELinux/AppArmor

4) Závěr

Repozitář je užitečný prototyp: ukazuje, jak injektovat API do klientského kódu, trackovat logy a spouštět skripty na události či cron. V současném stavu ale nesplňuje plně bezpečnostní a provozní nároky produkce – chybí OS sandbox, monitoring zdrojů je nedokončen a DX editoru je minimální. Doporučuji runtime oddělit (Deno/Wasm), doplnit chybějící komponenty, zautomatizovat build V8Js (nebo se ho zbavit) a posílit testy i CI pipeline. Tak dosáhnete robustní, škálovatelné a bezpečné in‑app scripting platformy připravené pro reálné klienty.
