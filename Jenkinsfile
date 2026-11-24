pipeline {
    agent any

    options {
        skipDefaultCheckout(true)
        disableConcurrentBuilds()
    }

    stages {
        stage('Obtener código') {
            steps {
                checkout scm
            }
        }

        stage('Pruebas y validaciones') {
            steps {
                sh '''
                    docker run --rm -v "$WORKSPACE:/app" -w /app node:20-alpine \
                      sh -c 'npm ci && rm -rf dist && npm run build && npm test'

                    docker run --rm -v "$WORKSPACE:/app" -w /app php:8.2-apache \
                      sh -c "find . -type f -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l"

                    docker compose -p "inventario-ci-$BUILD_NUMBER" config -q
                '''
            }
        }

        stage('Construir imagen Docker') {
            steps {
                sh '''
                    docker build -t sistema-inventario:$BUILD_NUMBER \
                      -t sistema-inventario:latest .
                    docker image ls sistema-inventario
                '''
            }
        }

        stage('Iniciar aplicación y MySQL') {
            steps {
                sh 'docker compose -p "inventario-ci-$BUILD_NUMBER" up -d --wait --wait-timeout 120 --no-build'
            }
        }

        stage('Comprobar aplicación y base de datos') {
            steps {
                sh '''
                    docker compose -p "inventario-ci-$BUILD_NUMBER" exec -T web \
                      php -r '$pagina = @file_get_contents("http://127.0.0.1/public/"); if ($pagina === false || strpos($pagina, "FlexiAlmacen") === false) { exit(1); } echo "Frontend correcto\\n";'

                    docker compose -p "inventario-ci-$BUILD_NUMBER" exec -T web \
                      php -r 'require "config/database.php"; db(); echo "MySQL conectado\\n";'

                    docker compose -p "inventario-ci-$BUILD_NUMBER" ps
                '''
            }
        }
    }

    post {
        failure {
            sh 'docker compose -p "inventario-ci-$BUILD_NUMBER" logs --tail 100 || true'
        }
        always {
            sh 'docker compose -p "inventario-ci-$BUILD_NUMBER" down --volumes --remove-orphans || true'
        }
    }
}