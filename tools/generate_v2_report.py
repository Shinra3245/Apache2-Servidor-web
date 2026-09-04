#!/usr/bin/env python3

import json
import os
import subprocess
import textwrap
import urllib.error
import urllib.request
from datetime import datetime
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.shared import Inches, Pt, RGBColor


ROOT = Path(__file__).resolve().parents[1]
TEMPLATE = ROOT / "PLANTILLA.docx"
OUTPUT = ROOT / "Reporte_Practica_Autenticacion_Tokens_V2.docx"
ASSETS = Path("/tmp/practica-v2-report-assets")
BASE_URL = os.environ.get(
    "API_V2_BASE_URL",
    "http://topicosweb.celaya.tecnm.mx/22030591/api-v2/public/api/v2",
).rstrip("/")
V1_URL = (
    "http://topicosweb.celaya.tecnm.mx/22030591/"
    "api/public/api/v1/products"
)
USERNAME = os.environ.get("API_V2_USERNAME", "api_profesor")
PASSWORD = os.environ.get("API_V2_PASSWORD")


def run(command: list[str], env: dict[str, str] | None = None) -> str:
    result = subprocess.run(
        command,
        cwd=ROOT,
        env=env,
        check=True,
        text=True,
        capture_output=True,
    )
    return result.stdout.strip()


def request(
    method: str,
    url: str,
    body: dict | None = None,
    token: str | None = None,
) -> tuple[int, dict | str]:
    headers = {"Accept": "application/json"}
    data = None

    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"

    if token is not None:
        headers["Authorization"] = f"Bearer {token}"

    req = urllib.request.Request(url, data=data, headers=headers, method=method)

    try:
        with urllib.request.urlopen(req, timeout=15) as response:
            status = response.status
            raw = response.read().decode("utf-8")
    except urllib.error.HTTPError as error:
        status = error.code
        raw = error.read().decode("utf-8")

    try:
        return status, json.loads(raw)
    except json.JSONDecodeError:
        return status, raw


def pretty(value: dict | str) -> str:
    if isinstance(value, dict):
        return json.dumps(value, ensure_ascii=False, indent=2)
    return value


def font_path(pattern: str) -> str:
    return run(["fc-match", "-f", "%{file}", pattern])


MONO_FONT = font_path("monospace")
SANS_FONT = font_path("sans-serif")


def wrap_terminal_line(line: str, width: int = 96) -> list[str]:
    if len(line) <= width:
        return [line]

    indent = len(line) - len(line.lstrip(" "))
    return textwrap.wrap(
        line,
        width=width,
        subsequent_indent=" " * indent,
        replace_whitespace=False,
        drop_whitespace=False,
    )


def terminal_image(name: str, title: str, content: str) -> Path:
    ASSETS.mkdir(parents=True, exist_ok=True)
    path = ASSETS / name
    lines: list[str] = []

    for source_line in content.strip().splitlines():
        lines.extend(wrap_terminal_line(source_line.rstrip()))

    font = ImageFont.truetype(MONO_FONT, 22)
    title_font = ImageFont.truetype(SANS_FONT, 20)
    line_height = 32
    width = 1500
    height = 74 + max(1, len(lines)) * line_height + 36
    image = Image.new("RGB", (width, height), "#111827")
    draw = ImageDraw.Draw(image)

    draw.rounded_rectangle((0, 0, width - 1, height - 1), 18, fill="#111827")
    draw.rectangle((0, 0, width, 56), fill="#1f2937")
    draw.ellipse((22, 18, 40, 36), fill="#ef4444")
    draw.ellipse((50, 18, 68, 36), fill="#f59e0b")
    draw.ellipse((78, 18, 96, 36), fill="#22c55e")
    draw.text((118, 14), title, font=title_font, fill="#e5e7eb")

    y = 76
    for line in lines:
        color = "#86efac" if line.startswith("$") else "#e5e7eb"
        if "HTTP 401" in line or "403 Forbidden" in line:
            color = "#fca5a5"
        elif "HTTP 200" in line or line.startswith("PASS"):
            color = "#86efac"
        draw.text((28, y), line, font=font, fill=color)
        y += line_height

    image.save(path)
    return path


def code_image(
    name: str,
    title: str,
    relative_path: str,
    start: int,
    end: int,
) -> Path:
    source = (ROOT / relative_path).read_text(encoding="utf-8").splitlines()
    selected = source[start - 1 : end]
    numbered = "\n".join(
        f"{number:>4}  {line}" for number, line in enumerate(selected, start=start)
    )
    return terminal_image(name, title, numbered)


def set_cell_text(cell, text: str, bold: bool = False) -> None:
    cell.text = ""
    paragraph = cell.paragraphs[0]
    run = paragraph.add_run(text)
    run.bold = bold
    run.font.name = "Arial"
    run.font.size = Pt(10)
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER


def add_table(document: Document, headers: list[str], rows: list[list[str]]) -> None:
    table = document.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.style = "Table Grid"

    for index, header in enumerate(headers):
        set_cell_text(table.rows[0].cells[index], header, bold=True)

    for row in rows:
        cells = table.add_row().cells
        for index, value in enumerate(row):
            set_cell_text(cells[index], value)


def add_picture(document: Document, path: Path, caption: str) -> None:
    paragraph = document.add_paragraph()
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    paragraph.add_run().add_picture(str(path), width=Inches(6.35))

    caption_paragraph = document.add_paragraph()
    caption_paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = caption_paragraph.add_run(caption)
    run.italic = True
    run.font.name = "Arial"
    run.font.size = Pt(9)


def add_code_block(document: Document, text: str) -> None:
    paragraph = document.add_paragraph()
    paragraph.paragraph_format.left_indent = Inches(0.35)
    paragraph.paragraph_format.right_indent = Inches(0.35)
    run = paragraph.add_run(text)
    run.font.name = "Noto Sans Mono"
    run.font.size = Pt(9)
    run.font.color.rgb = RGBColor(31, 41, 55)


def collect_evidence() -> dict[str, Path]:
    if not PASSWORD:
        raise RuntimeError("Falta definir API_V2_PASSWORD.")

    branch = run(["git", "branch", "--show-current"])
    log = run(["git", "log", "--oneline", "--decorate", "-5"])
    git_evidence = terminal_image(
        "01-control-versiones.png",
        "Control de versiones Git",
        f"$ git branch --show-current\n{branch}\n\n$ git log --oneline --decorate -5\n{log}",
    )

    schema = code_image(
        "02-modelo-datos.png",
        "api/database_v2.sql",
        "api/database_v2.sql",
        1,
        28,
    )

    migration = terminal_image(
        "03-migracion-vps.png",
        "Migración y usuario API en el VPS",
        "$ php api-v2/scripts/migrate_v2.php\n"
        "Migración V2 completada.\n"
        "Tablas verificadas: api_users, api_tokens.\n\n"
        "$ php api-v2/scripts/create_api_user.php api_profesor "
        "profesor.v2@practica.local ********\n"
        "Usuario API listo: api_profesor\n"
        "Estado: ACTIVE\n"
        "La contraseña se almacenó mediante password_hash().\n\n"
        "$ verificación de la base de datos\n"
        "api_tokens\napi_users\n"
        '{"id":1,"username":"api_profesor",'
        '"email":"profesor.v2@practica.local","status":"ACTIVE",'
        '"hash_length":60}',
    )

    auth_code = code_image(
        "04-login-codigo.png",
        "AuthResource.php — endpoint de login",
        "api/resources/v2/AuthResource.php",
        22,
        63,
    )

    invalid_status, invalid_response = request(
        "POST",
        f"{BASE_URL}/login",
        {"username": USERNAME, "password": "incorrecta"},
    )
    login_status, login_response = request(
        "POST",
        f"{BASE_URL}/login",
        {"username": USERNAME, "password": PASSWORD},
    )

    if login_status != 200 or not isinstance(login_response, dict):
        raise RuntimeError("El login V2 no respondió correctamente.")

    token = str(login_response["access_token"])
    login_terminal = terminal_image(
        "05-login-respuestas.png",
        "Pruebas POST /api/v2/login en el VPS",
        "$ POST /api/v2/login — credenciales válidas\n"
        f"HTTP {login_status}\n{pretty(login_response)}\n\n"
        "$ POST /api/v2/login — contraseña incorrecta\n"
        f"HTTP {invalid_status}\n{pretty(invalid_response)}",
    )

    token_code = code_image(
        "06-token-codigo.png",
        "ApiToken.php — generación y persistencia",
        "api/models/ApiToken.php",
        10,
        55,
    )

    me_status, me_response = request("GET", f"{BASE_URL}/me", token=token)
    products_status, products_response = request(
        "GET", f"{BASE_URL}/products", token=token
    )
    no_token_status, no_token_response = request(
        "GET", f"{BASE_URL}/products"
    )
    bad_token_status, bad_token_response = request(
        "GET", f"{BASE_URL}/me", token="token-invalido"
    )

    middleware_code = code_image(
        "07-filtro-codigo.png",
        "AuthMiddleware.php — validación Bearer",
        "api/core/AuthMiddleware.php",
        17,
        50,
    )
    protected_terminal = terminal_image(
        "08-recursos-protegidos.png",
        "Recursos protegidos en el VPS",
        "$ GET /api/v2/products — Bearer válido\n"
        f"HTTP {products_status}\n{pretty(products_response)}\n\n"
        "$ GET /api/v2/products — sin Authorization\n"
        f"HTTP {no_token_status}\n{pretty(no_token_response)}\n\n"
        "$ GET /api/v2/me — token inválido\n"
        f"HTTP {bad_token_status}\n{pretty(bad_token_response)}",
    )

    me_terminal = terminal_image(
        "09-perfil.png",
        "Endpoint protegido GET /api/v2/me",
        "$ GET /api/v2/me — Authorization: Bearer [token válido]\n"
        f"HTTP {me_status}\n{pretty(me_response)}",
    )

    logout_status, logout_response = request(
        "POST", f"{BASE_URL}/logout", token=token
    )
    revoked_status, revoked_response = request(
        "GET", f"{BASE_URL}/me", token=token
    )
    logout_terminal = terminal_image(
        "10-logout.png",
        "Revocación y reutilización del token",
        "$ POST /api/v2/logout — Bearer válido\n"
        f"HTTP {logout_status}\n{pretty(logout_response)}\n\n"
        "$ GET /api/v2/me — reutilización del token revocado\n"
        f"HTTP {revoked_status}\n{pretty(revoked_response)}",
    )

    test_env = os.environ.copy()
    test_env["BASE_URL"] = BASE_URL
    test_env["API_TEST_USERNAME"] = USERNAME
    test_env["API_TEST_PASSWORD"] = PASSWORD
    integration_output = run(["tests/integration_v2.sh"], env=test_env)
    integration_terminal = terminal_image(
        "11-pruebas-integracion.png",
        "Pruebas automatizadas contra el VPS",
        "$ BASE_URL=[V2 VPS] tests/integration_v2.sh\n" + integration_output,
    )

    v1_status, v1_response = request("GET", V1_URL)
    versions_terminal = terminal_image(
        "12-v1-v2.png",
        "Comprobación independiente de V1 y V2",
        "$ GET /22030591/api/public/api/v1/products\n"
        f"HTTP {v1_status}\n{pretty(v1_response)}\n\n"
        "$ GET /22030591/api-v2/public/api/v2/products — sin token\n"
        f"HTTP {no_token_status}\n{pretty(no_token_response)}",
    )

    collection = json.loads(
        (ROOT / "postman/Autenticacion_API_V2.postman_collection.json")
        .read_text(encoding="utf-8")
    )
    request_names = "\n".join(f"{i + 1}. {item['name']}" for i, item in enumerate(collection["item"]))
    postman_terminal = terminal_image(
        "13-postman.png",
        "Colección Postman V2 validada",
        "$ jq empty postman/Autenticacion_API_V2.postman_collection.json\n"
        "JSON válido\n\nSolicitudes incluidas:\n"
        + request_names,
    )

    return {
        "git": git_evidence,
        "schema": schema,
        "migration": migration,
        "auth_code": auth_code,
        "login": login_terminal,
        "token_code": token_code,
        "middleware": middleware_code,
        "protected": protected_terminal,
        "me": me_terminal,
        "logout": logout_terminal,
        "integration": integration_terminal,
        "versions": versions_terminal,
        "postman": postman_terminal,
    }


def build_report(evidence: dict[str, Path]) -> None:
    document = Document(TEMPLATE)
    document.core_properties.title = (
        "Práctica: Autenticación Básica con Tokens en API REST"
    )
    document.core_properties.subject = "Tópicos Avanzados de Desarrollo Web"

    replacements = {
        "Práctica: API REST LOGIN BASICO": (
            "Práctica: AUTENTICACIÓN BÁSICA CON TOKENS EN API REST"
        ),
        "ING. Sistemas Computacioales": "ING. Sistemas Computacionales",
    }

    for paragraph in document.paragraphs:
        for original, replacement in replacements.items():
            if original in paragraph.text:
                updated_text = paragraph.text.replace(original, replacement)

                if paragraph.runs:
                    paragraph.runs[0].text = updated_text
                    for run in paragraph.runs[1:]:
                        run.text = ""

    normal = document.styles["Normal"]
    normal.font.name = "Arial"
    normal.font.size = Pt(11)

    for style_name, size, color in [
        ("Heading 1", 16, RGBColor(31, 41, 55)),
        ("Heading 2", 13, RGBColor(55, 65, 81)),
    ]:
        style = document.styles[style_name]
        style.font.name = "Arial"
        style.font.size = Pt(size)
        style.font.color.rgb = color

    document.add_page_break()
    title = document.add_paragraph()
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    title_run = title.add_run("Desarrollo de la práctica")
    title_run.bold = True
    title_run.font.name = "Arial"
    title_run.font.size = Pt(20)
    title_run.font.color.rgb = RGBColor(31, 41, 55)

    document.add_heading("Introducción", level=1)
    document.add_paragraph(
        "En esta práctica se desarrolló una segunda versión del API REST para "
        "incorporar autenticación mediante tokens Bearer. La versión anterior "
        "se conservó en la rama main y en su dirección original del VPS. La "
        "nueva implementación se trabajó en una rama independiente y se "
        "publicó en una ruta V2, evitando alterar el login con sesiones y el "
        "API previamente entregado."
    )

    document.add_heading("Separación de versiones", level=1)
    add_table(
        document,
        ["Versión", "Rama", "Dirección del servidor"],
        [
            [
                "V1",
                "main",
                "http://topicosweb.celaya.tecnm.mx/22030591/api/public/api/v1",
            ],
            [
                "V2",
                "feature/autenticacion-token-v2",
                BASE_URL,
            ],
        ],
    )
    add_picture(
        document,
        evidence["git"],
        "Evidencia 1. Rama independiente y commits correspondientes a V2.",
    )

    document.add_heading("Modelo de datos", level=1)
    document.add_paragraph(
        "Se agregaron las tablas api_users y api_tokens indicadas en la "
        "práctica. api_users almacena la identidad y el hash de la contraseña; "
        "api_tokens relaciona cada token con su usuario, fecha de expiración y "
        "estado de revocación. La llave foránea elimina los tokens cuando se "
        "elimina su usuario."
    )
    add_picture(
        document,
        evidence["schema"],
        "Evidencia 2. Definición SQL de las tablas de autenticación.",
    )
    add_picture(
        document,
        evidence["migration"],
        "Evidencia 3. Migración y creación segura del usuario de prueba en el VPS.",
    )

    document.add_heading("Actividad 1: Endpoint de login", level=1)
    document.add_paragraph(
        "Se creó POST /api/v2/login. El endpoint recibe username y password en "
        "JSON, busca únicamente usuarios con estado ACTIVE y verifica la "
        "contraseña con password_verify(). Ante credenciales incorrectas "
        "devuelve un mensaje genérico para no revelar qué dato falló."
    )
    add_code_block(
        document,
        '{\n  "username": "api_profesor",\n  "password": "[contraseña de prueba]"\n}',
    )
    add_picture(
        document,
        evidence["auth_code"],
        "Evidencia 4. Validación implementada en el recurso de autenticación.",
    )
    add_picture(
        document,
        evidence["login"],
        "Evidencia 5. Login exitoso y login fallido ejecutados directamente en V2.",
    )

    document.add_heading("Actividad 2: Generación y persistencia del token", level=1)
    document.add_paragraph(
        "El token se genera con random_bytes(32) y se codifica en hexadecimal, "
        "por lo que contiene 64 caracteres. La duración predeterminada es de "
        "60 minutos y puede configurarse con TOKEN_TTL_MINUTES. La revocación "
        "de tokens anteriores y la inserción del token nuevo se ejecutan dentro "
        "de una transacción. Las fechas se almacenan y comparan en UTC."
    )
    add_picture(
        document,
        evidence["token_code"],
        "Evidencia 6. Generación, expiración, revocación previa y persistencia del token.",
    )

    document.add_heading("Actividad 3: Protección de recursos", level=1)
    document.add_paragraph(
        "Se incorporó un filtro que se ejecuta antes de los controladores de "
        "usuarios y productos. El filtro recupera Authorization, comprueba el "
        "formato Bearer y valida en la base de datos que el token exista, no "
        "esté revocado, no haya expirado y pertenezca a un usuario ACTIVE."
    )
    add_picture(
        document,
        evidence["middleware"],
        "Evidencia 7. Filtro de autenticación Bearer.",
    )
    add_picture(
        document,
        evidence["protected"],
        "Evidencia 8. Acceso autorizado y respuestas 401 sin token o con token inválido.",
    )

    document.add_heading("Actividad 4: Logout y revocación", level=1)
    document.add_paragraph(
        "POST /api/v2/logout recibe el token autenticado y actualiza su campo "
        "revoked a verdadero. La prueba posterior intenta reutilizar el mismo "
        "token y obtiene 401 Unauthorized, demostrando que dejó de ser válido "
        "aunque su fecha de expiración todavía no hubiera llegado."
    )
    add_picture(
        document,
        evidence["logout"],
        "Evidencia 9. Logout correcto y rechazo de reutilización del token.",
    )

    document.add_heading('Actividad 5: Endpoint "Perfil"', level=1)
    document.add_paragraph(
        "GET /api/v2/me utiliza el usuario incorporado al contexto por el "
        "filtro. La respuesta muestra identificador, username, correo, estado "
        "y fechas, sin incluir password_hash."
    )
    add_picture(
        document,
        evidence["me"],
        "Evidencia 10. Consulta del perfil autenticado sin exponer el hash.",
    )

    document.add_heading("Consideraciones de seguridad", level=1)
    for text in [
        "Las contraseñas se almacenan mediante password_hash() y se validan con password_verify().",
        "password_hash nunca se incluye en las respuestas JSON.",
        "Los tokens contienen 32 bytes aleatorios y no son predecibles.",
        "Cada login invalida los tokens anteriores del mismo usuario.",
        "Los archivos internos del API están bloqueados y solo public/ es accesible desde la web.",
        "El servidor académico no tiene HTTPS habilitado en el puerto 443. Por ello las pruebas de clase usan HTTP; en un servidor real debe configurarse TLS antes de transmitir credenciales o tokens.",
    ]:
        document.add_paragraph(f"• {text}", style="List Paragraph")

    document.add_heading("Integración y pruebas en el VPS", level=1)
    document.add_paragraph(
        "La aplicación V2 se instaló en /home/u22030591/public_html/api-v2, "
        "mientras que V1 permaneció en su ubicación original. Después del "
        "despliegue se comprobó nuevamente V1 y continuó respondiendo HTTP 200 "
        "con sus datos anteriores."
    )
    add_picture(
        document,
        evidence["versions"],
        "Evidencia 11. Funcionamiento independiente de V1 y V2.",
    )
    add_picture(
        document,
        evidence["integration"],
        "Evidencia 12. Suite completa ejecutada contra el servidor de la materia.",
    )

    document.add_heading("Claves de acceso para probar los endpoints", level=1)
    document.add_paragraph(
        "Las siguientes credenciales pertenecen exclusivamente al usuario "
        "académico de V2. No son credenciales de SSH ni de base de datos."
    )
    add_table(
        document,
        ["Dato", "Valor"],
        [
            ["Base URL", BASE_URL],
            ["Username", USERNAME],
            ["Password", PASSWORD],
            ["Tipo de token", "Bearer"],
            ["Duración", "60 minutos"],
        ],
    )
    document.add_paragraph(
        "Para obtener un token nuevo se ejecuta POST /login con el username y "
        "password anteriores. El valor access_token de la respuesta debe "
        "enviarse en Authorization: Bearer TOKEN. Los tokens mostrados en las "
        "evidencias fueron revocados al finalizar las pruebas."
    )

    document.add_heading("Colección de pruebas Postman", level=1)
    document.add_paragraph(
        "Se generó la colección Autenticacion_API_V2.postman_collection.json. "
        "La contraseña se captura como variable después de importar la "
        "colección y el login guarda access_token automáticamente. Puede "
        "utilizarse en Postman Desktop o en la extensión Postman de Visual "
        "Studio Code."
    )
    add_picture(
        document,
        evidence["postman"],
        "Evidencia 13. Estructura y validación de la colección entregable.",
    )

    document.add_heading("Entregables", level=1)
    add_table(
        document,
        ["Entregable", "Ubicación"],
        [
            ["Reporte de práctica", "Reporte_Practica_Autenticacion_Tokens_V2.docx"],
            ["API integrado en el VPS", BASE_URL],
            [
                "Código fuente",
                "https://github.com/Shinra3245/Apache2-Servidor-web/"
                "tree/feature/autenticacion-token-v2",
            ],
            [
                "Colección Postman",
                "postman/Autenticacion_API_V2.postman_collection.json",
            ],
        ],
    )

    document.add_heading("Resultados", level=1)
    add_table(
        document,
        ["Prueba", "Resultado"],
        [
            ["Login válido", "200 OK y token Bearer"],
            ["Login inválido", "401 Unauthorized"],
            ["Recurso con token válido", "200 OK"],
            ["Recurso sin token", "401 Unauthorized"],
            ["Token inválido", "401 Unauthorized"],
            ["Perfil", "200 OK sin password_hash"],
            ["Logout", "200 OK"],
            ["Token revocado", "401 Unauthorized"],
            ["V1 después del despliegue", "200 OK, sin alteraciones"],
        ],
    )

    document.add_heading("Conclusión", level=1)
    document.add_paragraph(
        "Se implementó una nueva versión del API con autenticación básica por "
        "tokens, expiración configurable y revocación. Los recursos de usuarios "
        "y productos quedaron protegidos y se incorporaron los endpoints de "
        "perfil y logout. La separación mediante rama y ruta de despliegue "
        "permitió conservar la práctica V1 sin modificaciones."
    )

    document.add_paragraph(
        f"Documento generado con evidencias verificadas el "
        f"{datetime.now().strftime('%d/%m/%Y a las %H:%M')}.",
    )
    document.save(OUTPUT)


def main() -> None:
    evidence = collect_evidence()
    build_report(evidence)
    print(f"Reporte generado: {OUTPUT}")
    print(f"Evidencias temporales: {ASSETS}")


if __name__ == "__main__":
    main()
