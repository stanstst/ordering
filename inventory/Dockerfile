FROM python:3.12-slim

WORKDIR /app

COPY inventory/requirements.txt ./inventory/requirements.txt
RUN pip install --no-cache-dir -r inventory/requirements.txt

# Inventory now writes directly into order_step_results, which lives in
# the Order service's own database - so it needs shared/ for the DB
# connection and the OrderStepResult model.
COPY shared ./shared
COPY inventory ./inventory

CMD ["python", "-m", "inventory.main"]
