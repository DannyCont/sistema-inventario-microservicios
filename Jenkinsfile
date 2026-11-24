/*
 * Jenkinsfile - Pipeline de Integración Continua (CI/CD)
 *
 * Práctica: GitHub + Docker + Jenkins
 * Proyecto: Sistema de inventario (FlexiAlmacen) con microservicios PHP
 *
 * Etapas:
 *   1. Checkout             -> obtiene el código fuente desde GitHub
 *   2. Verificar herramientas -> valida Docker y Docker Compose
 *   3. Pruebas estáticas    -> php -l sobre todos los archivos PHP (en un contenedor)
 *   4. Construir imagen     -> docker build con nombre y etiqueta (tag)
 *   5. Desplegar            -> docker compose up (web + MySQL)
 *   6. Pruebas funcionales  -> smoke test HTTP + login contra la base de datos
 *   7. Evidencia            -> docker ps / docker images / contenedor desplegado
 *
 * Nota: el agente de Jenkins corre como servicio de Windows (LocalSystem) y no
 * tiene el comando `docker` en el PATH, por eso se usan las rutas completas.
 * Si el agente fuera Linux, basta con usar `sh 'docker ...'`.
 */

pipeline {
    agent any

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        disableConcurrentBuilds()
    }

    environment {
        DOCKER               = 'C:\\Users\\user\\AppData\\Local\\Programs\\DockerDesktop\\resources\\bin\\docker.exe'
        COMPOSE              = 'C:\\Users\\user\\AppData\\Local\\Programs\\DockerDesktop\\resources\\bin\\docker-compose.exe'
        IMAGE_NAME           = 'sistema-inventario'
        APP_PORT             = '8082'
        // Proyecto de compose distinto al del uso manual, para no mezclar contenedores
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
                bat '"%DOCKER%" --version'
                bat '"%COMPOSE%" --version'
            }
        }

        stage('3. Pruebas estáticas (php -l)') {
            steps {
                bat '''
                    "%DOCKER%" run --rm -v "%WORKSPACE%:/app" -w /app php:8.2-apache sh -c "find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l"
                '''
            }
        }

        stage('4. Construir imagen Docker') {
            steps {
                bat '"%DOCKER%" build -t %IMAGE_NAME%:%BUILD_NUMBER% -t %IMAGE_NAME%:latest .'
                bat '"%DOCKER%" image ls %IMAGE_NAME%'
            }
        }

        stage('5. Desplegar contenedores') {
            steps {
                bat '"%COMPOSE%" down --remove-orphans || exit 0'
                bat '"%COMPOSE%" up -d --remove-orphans'
                bat '"%COMPOSE%" ps'
            }
        }

        stage('6. Pruebas funcionales') {
            steps {
                bat 'powershell -NoProfile -ExecutionPolicy Bypass -File ci\\smoke-test.ps1 -BaseUrl http://localhost:%APP_PORT%'
            }
        }

        stage('7. Evidencia del despliegue') {
            steps {
                bat '"%COMPOSE%" ps'
                bat '"%DOCKER%" image ls %IMAGE_NAME%'
                bat '"%DOCKER%" inspect --format "{{.Name}} -> {{.Config.Image}} ({{.State.Status}})" sistema-inventario-ci-web-1'
            }
        }
    }

    post {
        success {
            echo "Pipeline exitoso. Aplicación disponible en http://localhost:${APP_PORT}/public/"
        }
        unstable {
            echo 'El pipeline terminó inestable.'
        }
        failure {
            echo 'El pipeline falló. Revisando los logs de los contenedores...'
            bat '"%COMPOSE%" logs --tail=150 || exit 0'
        }
        always {
            bat '"%COMPOSE%" ps || exit 0'
        }
    }
}
