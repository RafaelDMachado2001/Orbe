# Orbe

## Objetivo do projeto

Meu objetivo com o Orbe é oferecer uma aplicação web de gestão financeira pessoal que ajude você a centralizar contas, bancos, cartões, lançamentos, categorias, metas, orçamentos, importações e relatórios em uma única interface. Você também pode acompanhar previsões de fluxo de caixa e alertas operacionais.

## Arquitetura do projeto

Eu organizei o projeto em dois módulos principais e serviços Docker para que você possa executar a aplicação de forma consistente:

- `frontend/`: aplicação React com Vite e TypeScript. Aqui você encontra a interface, a navegação, a autenticação e o consumo da API.
- `backend/`: API Laravel organizada por domínios de negócio, com autenticação via Sanctum, persistência, filas, comandos agendados e testes automatizados.
- `docker/`: imagens e configurações dos containers PHP, Node.js, Nginx e inicialização do banco.
- `docker-compose.yml`: arquivo que uso para orquestrar API, frontend, Nginx, PostgreSQL e Redis.

Quando você acessa a aplicação, o fluxo principal é: navegador → frontend React → Nginx → API Laravel → PostgreSQL/Redis. Filas e agendamentos são processados por workers separados do serviço principal da API.

## Tecnologias utilizadas

- Frontend: React 18, TypeScript, Vite, React Router, React Query, React Hook Form, Zod, Tailwind CSS e Recharts.
- Backend: PHP 8.3, Laravel 12, Laravel Sanctum, Pest/PHPUnit, Larastan e Laravel Pint.
- Infraestrutura: Docker Compose, Nginx, PostgreSQL 16, Redis 7 e Node.js 22.

### Execução por sistema operacional

Para começar, você precisa do Docker Desktop (Windows/macOS) ou do Docker Engine + Docker Compose (Linux).

Linux e macOS:

```bash
cp .env.example .env
docker compose up -d --build
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
docker compose up -d --build
```

Se você usa Windows, também pode executar os comandos Linux pelo Git Bash ou WSL. Depois de subir os containers, acesse a aplicação em `http://localhost:5173` e a API em `http://localhost:8000`.
