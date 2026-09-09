# DNS wildcard local (dnsmasq)

Servidor DNS dockerizado que resuelve `*.proyecto-grado-unad.test` a `127.0.0.1`, para poder probar el
registro público de tenants (`/comenzar`) con subdominios
reales en el navegador, sin depender de un servicio público (`localtest.me`/`nip.io`) ni de
editar `/etc/hosts` por cada slug nuevo.

## Por qué wildcard y no `/etc/hosts`

`/etc/hosts` (o su equivalente en Windows) solo resuelve nombres exactos — habría que agregar
una línea por cada tenant que se cree. Un slug nuevo creado desde `/comenzar` (ej.
`mi-negocio.proyecto-grado-unad.test`) tiene que funcionar de inmediato, sin tocar nada — por eso hace falta
un servidor DNS de verdad con una regla wildcard (`address=/proyecto-grado-unad.test/127.0.0.1` en
`dnsmasq.conf`).

## Levantarlo

```bash
export WSL_DNS_BIND_IP=$(hostname -I | awk '{print $1}')
docker compose --profile dns up -d dns
```

**El `export WSL_DNS_BIND_IP=...` es obligatorio en WSL2, no opcional.** El puerto 53 en
`0.0.0.0` (el default de cualquier `ports:` de Docker) **no se puede publicar en WSL2** —
`systemd-resolved` ya lo tiene ocupado en varias IPs específicas (`127.0.0.53`, `127.0.0.54`,
`10.255.255.254`), y Docker falla en silencio (el contenedor queda "Up" pero sin el puerto
realmente enlazado — no vas a ver ningún error, solo dejará de resolver). `WSL_DNS_BIND_IP`
hace que se publique en la IP de la interfaz LAN de la VM de WSL2 en cambio (verificado: no
choca con nada de `systemd-resolved`) — que además es la misma IP que ve Windows directamente,
así que sirve doble propósito.

Verificado en este entorno: `docker compose --profile dns up -d dns` sin la variable exportada
deja el contenedor corriendo pero con el puerto sin publicar de verdad
(`docker inspect proyecto-grado-unad-dns-1 --format '{{json .NetworkSettings.Ports}}'` muestra arrays
vacíos); con `WSL_DNS_BIND_IP` sí lo publica y resuelve.

Si no estás en WSL2 (Linux nativo, Mac) probablemente no tengas ese conflicto — en ese caso
alcanza con `docker compose --profile dns up -d dns` sin exportar nada (cae al default
`0.0.0.0` codeado en `compose.yaml`).

`WSL_DNS_BIND_IP` no se guarda en ningún `.env*` — es específica de esta máquina/sesión de WSL2
y puede cambiar entre reinicios (salvo con red *mirrored*, ver más abajo), así que hay que
exportarla de nuevo cada vez que abrís una terminal nueva antes de levantar el servicio (o
agregar el `export` a tu `~/.bashrc`/`~/.zshrc` si querés que quede fijo).

## Verificar que funciona (antes de tocar nada en Windows)

```bash
# Desde WSL, contra la IP publicada:
docker compose exec dns nslookup mi-negocio.proyecto-grado-unad.test 127.0.0.1
# debe responder 127.0.0.1

docker compose exec dns nslookup google.com 127.0.0.1
# debe responder una IP real (confirma que el forwarding upstream funciona,
# ver "no-resolv" + "server=..." en dnsmasq.conf)
```

## Cómo se resuelve la conexión real

1. El navegador (en Windows, si corrés en WSL2) consulta DNS para `algo.proyecto-grado-unad.test`.
2. Ese DNS (configurado en Windows, ver más abajo) apunta a la IP de `WSL_DNS_BIND_IP`.
3. `dnsmasq` responde `127.0.0.1` para **cualquier** subdominio de `proyecto-grado-unad.test` — un wildcard,
   no una lista.
4. El navegador conecta a `127.0.0.1` — WSL2 reenvía ese `localhost` hacia el contenedor
   `nginx`, igual que ya pasa hoy con `http://localhost:8060`.
5. Nginx entrega la request a PHP-FPM sin saber nada de tenants — la resolución real del
   tenant (`GetDomainData`) pasa por el Host header (`algo.proyecto-grado-unad.test`), no por DNS ni por
   nginx.

## Configurar Windows para usarlo

Esto es configuración de red de **Windows**, fuera de Docker/WSL — hay que hacerlo a mano, no
se puede automatizar desde acá, y no lo puedo verificar yo mismo (no tengo acceso al lado
Windows del host).

1. Con el contenedor ya levantado y verificado (paso anterior), anotá la IP que usaste en
   `WSL_DNS_BIND_IP`.
2. En Windows: **Panel de control → Redes e Internet → Centro de redes y recursos
   compartidos → Cambiar configuración del adaptador** → clic derecho en el adaptador de red
   activo → **Propiedades → Protocolo de Internet versión 4 (TCP/IPv4) → Propiedades** →
   "Usar las siguientes direcciones de servidor DNS":
   - Servidor DNS preferido: la IP de `WSL_DNS_BIND_IP` (ej. `192.168.1.49`)
   - Servidor DNS alternativo: `8.8.8.8` (respaldo si el contenedor no está levantado)
3. Probar en el navegador: `http://mi-negocio.proyecto-grado-unad.test` (o el slug que hayas creado desde
   `/comenzar`) — debería cargar directo, sin editar nada más.

**Si la IP de WSL2 cambia** (reinicio de Windows/WSL sin red *mirrored*): repetir el `export` +
`up` con la IP nueva (`hostname -I`) y actualizar la config de DNS de Windows con esa IP. Con
red *mirrored* (`%UserProfile%\.wslconfig` → `[wsl2]` → `networkingMode=mirrored`, Windows 11
reciente) la IP de WSL2 coincide con la del host y no cambia entre reinicios.

**Revertir:** volver esa configuración de Windows a "Obtener la dirección del servidor DNS
automáticamente". El contenedor `dns` no interfiere con nada mientras Windows no esté
apuntando a él.

## Alcance

Este servicio es **opcional** y **solo para desarrollo local** — no se usa en producción (ahí
aplica DNS wildcard real de tu proveedor). No arranca con `docker compose up -d` a secas — está detrás de un profile
(`--profile dns`), a propósito, para no intentar publicar el puerto 53 en cada arranque normal
del stack.
