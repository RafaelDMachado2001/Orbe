# Orbe

## Objetivo do projeto

O Orbe é uma aplicação web de gestão financeira pessoal. Seu objetivo é centralizar contas, bancos, cartões, lançamentos, categorias, metas, orçamentos, importações e relatórios em uma única interface, com previsão de fluxo de caixa e alertas operacionais.

## Arquitetura do projeto

O projeto é organizado em dois módulos principais e executado por serviços Docker:

- `frontend/`: aplicação React com Vite e TypeScript, responsável pela interface, navegação, autenticação e consumo da API.
- `backend/`: API Laravel organizada por domínios de negócio, com autenticação via Sanctum, persistência, filas, comandos agendados e testes automatizados.
- `docker/`: imagens e configurações dos containers PHP, Node.js, Nginx e inicialização do banco.
- `docker-compose.yml`: orquestra API, frontend, Nginx, PostgreSQL e Redis.

O fluxo principal é: navegador → frontend React → Nginx → API Laravel → PostgreSQL/Redis. Filas e agendamentos são processados por workers separados do serviço principal da API.

## Tecnologias utilizadas

- Frontend: React 18, TypeScript, Vite, React Router, React Query, React Hook Form, Zod, Tailwind CSS e Recharts.
- Backend: PHP 8.3, Laravel 12, Laravel Sanctum, Pest/PHPUnit, Larastan e Laravel Pint.
- Infraestrutura: Docker Compose, Nginx, PostgreSQL 16, Redis 7 e Node.js 22.

### Execução por sistema operacional

Pré-requisitos: Docker Desktop (Windows/macOS) ou Docker Engine + Docker Compose (Linux).

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

Windows também pode usar os comandos Linux pelo Git Bash ou WSL. A aplicação ficará disponível em `http://localhost:5173` e a API em `http://localhost:8000`.
