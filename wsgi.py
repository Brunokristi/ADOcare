from core import create_app
app = create_app()
# gunicorn wsgi:app -b 0.0.0.0:8000 --workers 3
