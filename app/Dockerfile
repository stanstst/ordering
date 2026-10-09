FROM python:3.12-slim

WORKDIR /app

COPY app/requirements.txt app/requirements-dev.txt ./app/
RUN pip install --no-cache-dir -r app/requirements-dev.txt

COPY shared ./shared
COPY app ./app

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
