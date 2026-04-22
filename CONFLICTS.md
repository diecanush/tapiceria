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

## Si NO tenés repo local (solo GitHub web)

1. Abrí el PR y hacé click en **Resolve conflicts**.
2. Para cada archivo en conflicto dentro de `project-skeleton/`:
   - buscá los bloques marcados con:
     - `<<<<<<<`
     - `=======`
     - `>>>>>>>`
   - conservá la versión del PR (la que contiene:
     - estimación de insumos en `presupuesto_nuevo.php`,
     - `app_url()` corregido en `includes/helpers.php`,
     - limpieza de `render_page_end()` en `index.php`, `agenda.php`, `insumos.php`, `clientes.php`).
3. Eliminá todos los marcadores de conflicto.
4. Click en **Mark as resolved** archivo por archivo.
5. Click en **Commit merge**.
6. Volvé al PR y completá el merge.

Si GitHub no deja resolver por UI, la alternativa es:
- Crear un branch nuevo desde el branch del PR.
- Editar directamente esos archivos con el contenido final correcto.
- Abrir un PR nuevo limpio.
