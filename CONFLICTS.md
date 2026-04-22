# Resolver conflictos del PR (rápido)

Si GitHub muestra conflictos al mergear este branch, usá este flujo local:

```bash
git fetch origin
git checkout <tu-branch-del-pr>
git rebase origin/main
```

Si aparecen conflictos en archivos de `project-skeleton/`:

```bash
git checkout --theirs project-skeleton/presupuesto_nuevo.php
git checkout --theirs project-skeleton/includes/helpers.php
git checkout --theirs project-skeleton/assets/css/styles.css
git checkout --theirs project-skeleton/index.php
git checkout --theirs project-skeleton/agenda.php
git checkout --theirs project-skeleton/insumos.php
git checkout --theirs project-skeleton/clientes.php
git add project-skeleton/presupuesto_nuevo.php \
        project-skeleton/includes/helpers.php \
        project-skeleton/assets/css/styles.css \
        project-skeleton/index.php \
        project-skeleton/agenda.php \
        project-skeleton/insumos.php \
        project-skeleton/clientes.php
git rebase --continue
```

Al terminar:

```bash
git push --force-with-lease
```

> Nota: durante un **rebase**:
> - `--ours` = la versión de `main`
> - `--theirs` = la versión de tu commit del PR

Si preferís merge en vez de rebase:

```bash
git checkout <tu-branch-del-pr>
git merge origin/main
```

y resolvés conflictos igual (eligiendo los archivos del PR donde corresponda).
