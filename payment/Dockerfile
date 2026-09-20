FROM python:3.12-slim

WORKDIR /app

COPY payment/requirements.txt ./payment/requirements.txt
RUN pip install --no-cache-dir -r payment/requirements.txt

# Payment writes directly into order_step_results, which lives in the
# Order service's own database - so it needs shared/ for the DB connection
# and the OrderStepResult model.
COPY shared ./shared
COPY payment ./payment

CMD ["python", "-m", "payment.main"]
