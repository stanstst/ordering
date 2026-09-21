FROM python:3.12-slim

WORKDIR /app

COPY status_processor/requirements.txt ./status_processor/requirements.txt
RUN pip install --no-cache-dir -r status_processor/requirements.txt

# Reads Order and OrderStepResult - both live in the Order service's own
# database, so it needs shared/ for the DB connection and the models.
COPY shared ./shared
COPY status_processor ./status_processor

CMD ["python", "-m", "status_processor.main"]
