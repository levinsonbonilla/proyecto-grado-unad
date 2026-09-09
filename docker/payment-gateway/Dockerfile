FROM node:20-alpine

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev

COPY server.js ./
COPY views ./views

EXPOSE 8070

CMD ["sh", "-c", "sed -e '/^127\\.0\\.0\\.1[[:space:]]\\+localhost$/d' -e 's/^\\(::1[[:space:]]\\+\\)localhost[[:space:]]*/\\1/' /etc/hosts > /tmp/hosts.new && cat /tmp/hosts.new > /etc/hosts && exec node server.js"]
