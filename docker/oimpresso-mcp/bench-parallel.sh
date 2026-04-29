#!/bin/sh
# Bench 5 calls paralelas pra validar Octane workers
TOKEN="mcp_0db8039c296fbd8ac9d40f02a54bbbc86bcb56cc10c6e922731516e911ffc472"
URL="https://mcp.oimpresso.com/api/mcp"
echo "=== 5 calls paralelas tools/list ==="
for i in 1 2 3 4 5; do
    (time curl -s "$URL" -X POST \
        -H "Content-Type: application/json" \
        -H "Accept: application/json, text/event-stream" \
        -H "Authorization: Bearer $TOKEN" \
        -d "{\"jsonrpc\":\"2.0\",\"id\":$i,\"method\":\"tools/list\"}" \
        -o /dev/null --max-time 30) 2>&1 | grep -E "real|user|sys" | head -1 &
done
wait
echo "---"
echo "=== 10 calls paralelas tools/list ==="
for i in 1 2 3 4 5 6 7 8 9 10; do
    (time curl -s "$URL" -X POST \
        -H "Content-Type: application/json" \
        -H "Accept: application/json, text/event-stream" \
        -H "Authorization: Bearer $TOKEN" \
        -d "{\"jsonrpc\":\"2.0\",\"id\":$i,\"method\":\"tools/list\"}" \
        -o /dev/null --max-time 30) 2>&1 | grep -E "real" | head -1 &
done
wait
