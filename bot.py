import telebot
from telebot.types import InlineKeyboardMarkup, InlineKeyboardButton, ReactionTypeEmoji
import requests
import re
import urllib.parse
import random
import time
import threading
import os
from flask import Flask

# Aapka Bot Token
TOKEN = "8242456696:AAFcejLBmeNo96zAe9c9w83RR9H_vPceQ1s"
bot = telebot.TeleBot(TOKEN, parse_mode='HTML')

CHANNELS = ["@iSpeedX1", -1002914762713, -1002982705158]

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36"
]

def check_membership(user_id):
    for ch in CHANNELS:
        try:
            status = bot.get_chat_member(ch, user_id).status
            if status not in ['member', 'administrator', 'creator']:
                return False
        except Exception:
            return False
    return True

def force_sub_markup():
    markup = InlineKeyboardMarkup()
    btn1 = InlineKeyboardButton("Join Us", url="https://t.me/iSpeedX1")
    btn2 = InlineKeyboardButton("Join Us", url="https://t.me/+riAG4odKlns2OGNl")
    btn3 = InlineKeyboardButton("Join Us", url="https://t.me/+rf2XF4V31YFhMThl")
    btn4 = InlineKeyboardButton("✅ Joined", callback_data="check_joined")
    markup.row(btn1, btn2)
    markup.row(btn3, btn4)
    return markup

def send_welcome_message(chat_id):
    msg_text = (
        "<b>Welcome SpeedX™\n"
        "Send Below Task Link Here\n\n"
        "Tide\n"
        " ╰┈➤ <code>http://tracking.gridadss.com...</code>\n"
        "MEXC\n"
        " ╰┈➤ <code>http://tracking.gridadss.com...</code>\n"
        "Amazon\n"
        " ╰┈➤ <code>https://mobavenue.go2affise...</code></b>"
    )
    bot.send_message(chat_id, msg_text)

@bot.message_handler(commands=['start'])
def send_welcome(message):
    user_id = message.from_user.id
    if check_membership(user_id):
        send_welcome_message(message.chat.id)
    else:
        bot.send_message(message.chat.id, "<b>Please join our channels first to use this bot!</b>", reply_markup=force_sub_markup())

@bot.callback_query_handler(func=lambda call: call.data == "check_joined")
def check_joined_callback(call):
    if check_membership(call.from_user.id):
        bot.delete_message(call.message.chat.id, call.message.message_id)
        send_welcome_message(call.message.chat.id)
    else:
        bot.answer_callback_query(call.id, "You haven't joined all channels yet!", show_alert=True)

def extract_tri_value(url, session, headers):
    match = re.search(r'TRI\d*=([^&?\s"\'<>]+)', url)
    if match: return match.group(1)
    try:
        response = session.get(url, headers=headers, timeout=15, allow_redirects=True)
        match = re.search(r'TRI\d*=([^&?\s"\'<>]+)', response.url)
        if match: return match.group(1)
        for cookie in session.cookies:
            if "TRI" in cookie.name: return cookie.value
        match = re.search(r'TRI\d*=([^&?\s"\'<>]+)', response.text)
        if match: return match.group(1)
    except:
        pass
    return None

def process_task_background(message):
    chat_id = message.chat.id
    target_url = message.text.strip()
    processing_msg = bot.send_message(chat_id, "<b>Processing...</b>")
    
    while True:
        try:
            session = requests.Session()
            ua = random.choice(USER_AGENTS)
            headers = {"User-Agent": ua}
            
            # ==========================================
            # TASK 1: GRIDADSS (Tide & MEXC)
            # ==========================================
            if 'tracking.gridadss.com' in target_url:
                token = extract_tri_value(target_url, session, headers)
                if token:
                    final_token = urllib.parse.unquote(token)
                    postback_url = f"http://tracking.gridadss.com/conv?yeahmobi_ocpa&event=install&transaction_id={final_token}"
                    pb_res = requests.get(postback_url, headers=headers, timeout=15)
                    
                    if pb_res.status_code == 200:
                        bot.edit_message_text("<b>Task Complete Success!</b>", chat_id=chat_id, message_id=processing_msg.message_id)
                        break
            
            # ==========================================
            # TASK 2: AMAZON (Mobavenue)
            # ==========================================
            elif 'mobavenue.go2affise' in target_url:
                # Link open karo takii cookie generate ho sake
                session.get(target_url, headers=headers, timeout=20, allow_redirects=True)
                
                # Cookie se 'afclick' nikalna
                click_id = None
                for cookie in session.cookies:
                    if cookie.name == 'afclick':
                        click_id = cookie.value
                        break
                
                if click_id:
                    # Postback URL generate karke call karna
                    postback_url = f"https://offers-mobavenue.affise.com/postback?click_id={click_id}&goal_value=install"
                    
                    # Amazon task ka postback time leta hai isliye timeout thoda zyada rakha hai (30 sec)
                    pb_res = requests.get(postback_url, headers=headers, timeout=30)
                    
                    if pb_res.status_code == 200:
                        try:
                            # Response ko JSON me decode karke status verify karna
                            pb_json = pb_res.json()
                            if pb_json.get("status") == 1:
                                bot.edit_message_text("<b>Task Complete Success!</b>", chat_id=chat_id, message_id=processing_msg.message_id)
                                break
                        except Exception:
                            pass # JSON error aaya toh wapis loop chalega

        except Exception:
            pass
        
        # Thodi der ruk kar wapis try karega agar success nahi hua
        time.sleep(3)

@bot.message_handler(func=lambda message: message.text.startswith('http'))
def handle_link(message):
    user_id = message.from_user.id
    target_url = message.text.strip()
    
    if not check_membership(user_id):
        bot.send_message(message.chat.id, "<b>Please join our channels first!</b>", reply_markup=force_sub_markup())
        return
        
    # Check if the link belongs to either Gridadss OR Amazon (Mobavenue)
    is_gridadss = target_url.startswith('http://tracking.gridadss.com') or target_url.startswith('https://tracking.gridadss.com')
    is_amazon = 'mobavenue.go2affise' in target_url

    if not (is_gridadss or is_amazon):
        bot.send_message(message.chat.id, "<b>Invalid Task Link</b>")
        return
        
    try:
        bot.set_message_reaction(message.chat.id, message.message_id, [ReactionTypeEmoji('🔥')])
    except:
        pass
        
    threading.Thread(target=process_task_background, args=(message,)).start()

# ==========================================
# RENDER KE LIYE DUMMY FLASK SERVER
# ==========================================
app = Flask(__name__)

@app.route('/')
def home():
    return "Bot is running 24/7 on Render!"

def run_web():
    port = int(os.environ.get("PORT", 8080))
    app.run(host="0.0.0.0", port=port)

if __name__ == "__main__":
    # Flask ko background me chalu karo
    threading.Thread(target=run_web).start()
    print("Bot is running...")
    # Bot ko chalu karo
    bot.infinity_polling()
