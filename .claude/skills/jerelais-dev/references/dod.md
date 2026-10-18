# Definition of Done

**Fonctionnel**
- [ ] Exigence(s) du cahier des charges couverte(s) et citée(s)
- [ ] Textes en français dans `lang/fr`
- [ ] Interface responsive, lisible en plein soleil

**Sécurité**
- [ ] Policy/Gate sur chaque action, lecture comprise
- [ ] Périmètre commune appliqué
- [ ] Form Request + `$fillable`
- [ ] API Resource : aucun champ sensible inutile
- [ ] Action sensible auditée
- [ ] Aucune donnée personnelle dans les logs
- [ ] Rate limiting sur les routes exposées

**Qualité**
- [ ] Tests Pest : nominal, erreur, **refus d'accès** (autre commune / autre rôle), référence d'exigence dans le nom
- [ ] Pint, Larastan, tests verts
- [ ] Migration réversible, index posés
- [ ] Pas de N+1
- [ ] Traitements longs en file Horizon

**API et documentation**
- [ ] OpenAPI à jour, changement signalé à l'équipe mobile (`api-change`)
- [ ] Démo possible en recette
