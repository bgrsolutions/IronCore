# VeriFactu Official Sources (Release 6)

## 1) Real Decreto 1007/2023 (Reglamento de sistemas informáticos de facturación)
- **Title:** Real Decreto 1007/2023, de 5 de diciembre.
- **Publication / version:** BOE-A-2023-24840, publicado 06/12/2023.
- **Official URL:** https://www.boe.es/buscar/doc.php?id=BOE-A-2023-24840
- **Scope governed:** Marco reglamentario de requisitos antifraude del software de facturación, registros de facturación y trazabilidad.
- **Chain-scope notes:** Define encadenamiento de registros, pero en esta implementación se mantiene el alcance operativo por `(company_id + series)` (ver decisión de alcance abajo) hasta completar mapeo técnico de todos los anexos operativos AEAT.

## 2) Orden HAC/1177/2024 (desarrollo técnico y funcional)
- **Title:** Orden HAC/1177/2024, de 17 de octubre.
- **Publication / version:** BOE-A-2024-22138, publicado 28/10/2024.
- **Official URL:** https://www.boe.es/buscar/doc.php?id=BOE-A-2024-22138
- **Scope governed:** Especificaciones técnicas, funcionales y de contenido (incluye contenido de registros, huella y QR a nivel de anexos técnicos).
- **Chain-scope notes:** Es la fuente normativa principal usada para derivar canonicalización, huella SHA-256 y contenido mínimo de QR en esta release.

## 3) Portal oficial AEAT VeriFactu
- **Title:** Sistemas informáticos de facturación y VERI*FACTU (Sede AEAT).
- **Publication / version:** Página oficial viva (consultada en 2026-02-27).
- **Official URL:** https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html
- **Scope governed:** Hub oficial de obligaciones, calendario y enlaces técnicos de AEAT.
- **Chain-scope notes:** Referencia oficial operativa para documentación y materiales técnicos enlazados.

## 4) Información técnica AEAT (landing)
- **Title:** Información técnica (Sede AEAT, VeriFactu).
- **Publication / version:** Página oficial viva (consultada en 2026-02-27).
- **Official URL:** https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/informacion-tecnica.html
- **Scope governed:** Punto oficial para servicios y documentación técnica (anexos, formatos y servicios en evolución).
- **Chain-scope notes:** Se utiliza como referencia de tracking para futuras iteraciones de formato exacto.

---

## Chain Scope Decision Used in Code

### Implemented scope
- **Hash chain scope = (`company_id` + `series`)**.
- Each company has independent chains.
- Inside each company, each series has an independent chain.

### Rationale
- Requirement from project instructions applies this default unless AEAT explicitly enforces a different operational scope in a directly consumible technical artifact.
- Current implementation keeps this deterministic scope and records the decision in ERP docs.

---

## Documented Uncertainties and TODO

1. **Exact AEAT transport/export envelope**
   - Implemented export registry with deterministic JSON file (`verifactu_records`) and registry tracking.
   - **TODO:** align export envelope byte-by-byte with final AEAT machine interface artifact if a stricter schema/version is mandated in published technical packages.

2. **Exact QR payload canonical field packing**
   - Implemented mandatory operational payload fields (issuer tax id, invoice identifier, issue date, total, hash).
   - **TODO:** align parameter naming/order exactly to the final AEAT QR technical annex artifact in force at deployment date.

3. **Canonicalization field subset and ordering by annex version**
   - Implemented deterministic canonicalization from immutable invoice payload + chain context.
   - **TODO:** pin to exact annex version identifiers and field-level encoding constraints if AEAT publishes stricter examples/schemas.

4. **QR query-string encoding choice**
   - `http_build_query(..., PHP_QUERY_RFC3986)` is used to keep deterministic percent-encoding (`%20` instead of `+`).
   - **TODO:** switch only if AEAT annex explicitly mandates RFC1738 behavior for QR payload serialization.
