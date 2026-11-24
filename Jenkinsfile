/*
 * Jenkinsfile - Pipeline de Integración Continua (CI/CD)
 *
 * Práctica integradora: GitHub + Docker + Jenkins
 * Proyecto: Sistema de inventario (FlexiAlmacen) con microservicios PHP
 *
 * Etapas:
 *   1. Checkout            -> obtiene el código fuente desde GitHub
 *   2. Herramientas        -> valida Docker y Docker Compose
 *   3. Pruebas estáticas   -> php -l sobre todos los archivos PHP (dentro de un contenedor)
 *   4. Build imagen        -> docker build con nombre y etiqueta (tag)
 *   5. Despliegue          -> docker compose up (web + MySQL)
 *   6. Pruebas funcionales -> smoke test HTTP + login contra la base de datos
 *   7. Evidencia           -> docker ps / docker images / versión desplegada
 *
 * Nota: el agente de Jenkins corre sobre Windows, por eso se usa `bat`.
 *       Si el agente fuera Linux, basta con cambiar `bat` por `sh` y
 *       `%VARIABLE%` por $VARIABLE.
 */

pipeline {
    agent any

    options {
        timestamps()
        buildDiscarder(logRotator(numToKeepStr: '10'))
        disableConcurrentBuilds()
    }

    environment {
        IMAGE_NAME           = 'sistema-inventario'
        APP_PORT             = '8082'
        COMPOSE_PROJECT_NAME = 'sistema-inventario-ci'
    }

    stages {

        stage('1. Checkout') {
            steps {
                checkout scm
                bat 'git log -1 --pretty=format:"%h %s"'
            }
        }

        stage('2. Verificar herramientas') {
            steps {
                bat 'docker --version && docker compose version'
            }
        }

        stage('3. Pruebas estáticas (php -l)') {
            steps {
                bat '''
                    docker run --rm -v "%WORKSPACE%:/app" -w /app php:8.2-apache sh -c "find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l"
                '''
            }
        }

        stage('4. Construir imagen Docker') {
            steps {
                bat "docker build -t %IMAGE_NAME%:%BUILD_NUMBER% -t %IMAGE_NAME%:latest ."
                bat "docker image ls %IMAGE_NAME%"
            }
        }

        stage('5. Desplegar contenedores') {
            steps {
                bat 'docker compose down --remove-orphans || exit 0'
                bat 'docker compose up -d --remove-orphans'
                bat 'docker compose ps'
            }
        }

        stage('6. Pruebas funcionales') {
            steps {
                bat 'powershell -NoProfile -ExecutionPolicy Bypass -File ci\\smoke-test.ps1 -BaseUrl http://localhost:%APP_PORT%'
            }
        }

        stage('7. Evidencia del despliegue') {
            steps {
                bat 'docker compose ps'
                bat 'docker image ls %IMAGE_NAME%'
                bat 'docker inspect --format "{{.Name}} -> {{.Config.Image}}" sistema-inventario-ci-web-1'
            }
        }
    }

    post {
        success {
            echo "Pipeline OK. Aplicación disponible en http://localhost:${APP_PORT}/public/"
        }
        unstable {
            echo 'El pipeline terminó inestable.'
        }
        failure {
            echo 'El pipeline falló. Revisando logs de los contenedores...'
            bat 'docker compose logs --tail=150 || exit 0'
        }
        always {
            bat 'docker compose ps || exit 0'
        }
    }
}
