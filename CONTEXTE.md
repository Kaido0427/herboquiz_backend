# HerboQuiz — contexte du projet

Document de reprise. À coller en début de session pour travailler sur HerboQuiz
sans dépendre de l'historique Gextimo/NovafriQ.

---

## Ce que c'est

Application de gestion d'un **tournoi de quiz** pour la **TEAM DES HERBOGENISTES**,
un groupe d'herboristes au Bénin. Premier tournoi : **lundi 27 juillet 2026, 18h
(heure du Bénin)**.

**Le jeu ne se joue PAS dans l'application.** Il se joue dans un **groupe
Messenger** : l'animateur pose une question, laisse une fenêtre de réponse, et
**le premier qui répond juste marque**. Les phases finales passent sur WhatsApp.

L'application sert à **tenir les scores**, parce que le faire à la main sur un
téléphone pendant que le groupe attend est la vraie douleur de l'organisateur.

**Contrainte structurante** : beaucoup de participants n'ont pas de forfait data
(Messenger est dégroupé chez les opérateurs, pas le web). Donc **les joueurs
n'ont jamais besoin de l'application**. Elle informe, elle ne conditionne rien.

---

## Adresses et accès

| | |
|---|---|
| Site | https://herboquiz.novafriq.africa |
| Inscription publique | https://herboquiz.novafriq.africa/inscription |
| Connexion | https://herboquiz.novafriq.africa/connexion |

**Codes d'accès** (régénérables depuis l'admin, onglet Accès) :

- **Admin : `D959KX3Z`** — les 6 administrateurs partagent ce code
- **Animateur : `WJN4B4AL`** — régénérable après chaque soirée, les modos tournent

> Si un code est régénéré, celui noté ici devient faux. Le relire en base ou
> dans l'onglet Accès.

**Les 6 administrateurs pré-enregistrés** : Kaido (= Markus = le propriétaire du
projet, compte GitHub Kaido0427), Eckson Leroy, Benoît, Titus, Kévin HOUETO,
Eagle Owl.

---

## Dépôts et déploiement

Deux dépôts sur le compte GitHub **Kaido0427** (jamais devdjraaa) :

- `herboquiz_backend` — Laravel 13 + Sanctum, API seule, PostgreSQL
- `herboquiz_frontend` — React 19 + Vite + Tailwind 4

**Pousser en SSH via l'alias `github-kaido`.** Deux comptes GitHub coexistent sur
la machine ; `github-djraa` est l'autre et ne doit pas servir ici.

**CI/CD** : un `git push` sur `main` déclenche le déploiement (GitHub Actions →
rsync vers le VPS). Trois garde-fous délibérés dans le workflow backend :

- refus de déployer si le `.env` de production manque sur le serveur
- un seul déploiement à la fois (deux migrations simultanées se marcheraient dessus)
- vérification que l'API répond 200 en fin de course

Le workflow **ignore les fichiers `.md`** : modifier ce document ne déclenche
aucun déploiement, c'est voulu.

**VPS** : `ssh novafriq` (187.127.234.156). Chemins `/var/www/herboquiz_backend`
et `/var/www/herboquiz_frontend`. nginx + PHP 8.4-FPM. **Pas de Node sur le
serveur** — la construction se fait toujours en CI.

**Sauvegarde** : `/home/novafriq/sauvegarde-herboquiz.sh`, tous les jours à 3h,
vers `/var/backups/herboquiz`, conservation 14 jours. Vérifiée exploitable, pas
seulement existante. **Limite connue** : la copie est sur le même serveur que la
base — protège d'une erreur humaine, pas de la perte du serveur.

---

## Décisions structurantes, et pourquoi

Ces choix ont chacun une raison. Les défaire sans la connaître casserait quelque
chose.

**Les points sont un journal en ajout seul.** Le classement se calcule en
sommant, jamais un total stocké. On obtient ainsi gratuitement l'annulation,
l'historique, et surtout la **traçabilité** : chaque point garde le nom de
l'animateur qui l'a attribué. Avec de l'argent en jeu, une contestation se
tranche sur des faits horodatés.

**L'entité qui concourt est toujours une équipe**, même en solo (équipe d'une
personne). C'est ce qui permet de basculer solo → duo après les inscriptions
sans rien réécrire ni perdre un point.

**Un seul code par rôle, l'identité est saisie à la connexion.** Six admins
partagent un code sans qu'on perde la traçabilité. Le nom se **choisit** dans une
liste plutôt que se taper : « Eckson » un soir et « Ekson » le lendemain rendrait
l'historique inexploitable. ⚠️ **Limite** : le nom est déclaré, pas vérifié.
C'est de l'attribution, pas de l'authentification.

**Régénérer un code révoque les jetons déjà émis.** Sans ça, la rotation serait
cosmétique — l'ancien animateur resterait connecté.

**Zéro codage en dur.** Aucun texte, prix ou seuil ne vit dans le code : tout est
dans la table `reglages`, éditable depuis l'admin. Les textes de l'interface
passent par i18n (`src/lang/fr.json`), jamais un littéral dans le JSX.

**Le meilleur marqueur se calcule sur les POULES uniquement.** Sur le total brut,
celui qui atteint la finale joue plus de manches et accumule mécaniquement plus
de points : le prix reviendrait au vainqueur, ce qui viderait la récompense de
son sens.

**Le podium ne s'affiche pas tant qu'aucun point n'est marqué.** Un podium à zéro
laisse croire à un classement figé.

**L'animateur peut préparer ses questions** mais reste bloqué (403 côté serveur,
pas seulement masqué) sur les participants, les réglages, le format et les accès.

---

## Classe de bugs rencontrée — à surveiller

Trois défauts de la même famille dans la même journée : **l'application disait
que ça marchait alors que non.**

1. **Icône utilisée sans être importée** (`BarChart3`) → écran blanc, sans
   message. La construction ne signale rien : une balise JSX inconnue reste une
   référence de variable, résolue au rendu seulement.
2. **Bouton « terminer la manche »** : l'API basculait bien le statut, mais
   **rien ne changeait à l'écran** — on croyait le bouton cassé.
3. **`apiResource('manches')` singularisait en `{manch}`** : la liaison
   automatique ne trouvait jamais l'objet, `delete()` ne portait sur rien, et
   l'API répondait « Manche supprimée » avec un code 200. Un échec déguisé en
   succès. Routes réécrites à la main. Les autres ressources ne sont pas
   touchées (vérifié).

**Leçon opératoire** : un build vert ne prouve rien sur le comportement. Après
une modification par script, **vérifier que le remplacement a bien eu lieu**
avant de committer, pas seulement que ça compile.

**Autres pièges corrigés** : Sanctum crée sa table en identifiants entiers alors
que les modèles sont en UUID (`uuidMorphs`) ; les tables de liaison ne doivent
pas porter de clé primaire UUID (`attach()` ne la renseigne pas) ; la liste des
équipes doit avoir un **ordre stable**, sinon les boutons changent de place sous
le doigt de l'animateur en plein match.

---

## Règles du tournoi (paramétrables)

| Réglage | Valeur |
|---|---|
| Points par bonne réponse | **10** |
| Bonnes réponses pour gagner un duel | 5 |
| Bonnes réponses pour gagner la finale | 7 |
| Max par poule | 20 |
| Qualifiés par poule | 4 |
| Questions par manche | 12 à 20 |

⚠️ Le seuil d'un duel s'exprime en **bonnes réponses**, converti en points à
l'exécution. Sinon, passer la bonne réponse à 10 points terminerait un duel
« premier à 5 » dès la première question.

**Prix** (total 20 000 F) : 1er 6 000 · 2e 3 000 · 3e 2 000 · meilleur marqueur
2 000 · animateurs 7 000.

**Enchaînement des phases** : les qualifiés se déduisent de l'état réel
(classements de poule, puis vainqueurs du tour précédent). Tête de série contre
dernier qualifié. **Effectif impair → exemption pour le mieux classé**, sinon il
était éliminé en silence. Le **match pour la 3e place** se crée avec la finale,
pas avant : ses participants sont les perdants des demies, inconnus plus tôt.

**Égalités** : à points égaux, la rapidité tranche (qui a atteint son total en
premier). Si même la rapidité ne départage pas, l'admin est **alerté avant la
génération** pour poser un barrage.

---

## État au 23 juillet 2026

- **10 inscrits confirmés**, calendrier et classement vides
- Clôture des inscriptions : **samedi 25 juillet à 20h**
- Coup d'envoi : lundi 27 juillet, 18h
- **Banque de questions vide** — rien n'est encore préparé
- Le lien d'inscription **n'a pas encore été diffusé** dans le groupe

**Reste à faire :**

1. Diffuser le lien d'inscription (bloque tout le reste)
2. Préparer les questions — format `question | réponse`, une par ligne ;
   le point-virgule et la tabulation marchent aussi (copier-coller d'un tableur).
   L'écran indique **avant** l'import quelles lignes seront écartées.
3. Après clôture : simuler le format, appliquer, rattacher les questions
4. Corriger « Lundi 27 juillet 2026 a 18h00 » (accent manquant, reste d'une
   valeur par défaut)

**Jamais vérifié visuellement** : l'écran d'animation en conditions réelles. Les
données qui le traversent sont testées de bout en bout, le rendu ne l'est pas.

---

## Façon de travailler attendue

- Français, tutoiement, ton direct
- **Vérifier plutôt qu'affirmer** — tester en production après chaque déploiement
- Dire ce qui n'a pas été vérifié, ne pas déduire un succès d'un indice partiel
- Ne jamais supprimer en masse sur la production : cibler par identifiant.
  Un incident a déjà eu lieu (11 inscrits effacés par une boucle de nettoyage,
  récupérés grâce à la suppression douce)
- Commits en français, expliquant le **pourquoi** et pas seulement le quoi
- Pas d'emoji dans le code — icônes `lucide-react`
