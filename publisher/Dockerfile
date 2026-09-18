FROM python:3.12-slim

WORKDIR /app

COPY publisher/requirements.txt ./publisher/requirements.txt
RUN pip install --no-cache-dir -r publisher/requirements.txt

COPY shared ./shared
COPY publisher ./publisher

CMD ["python", "-m", "publisher.main"]
