// Painel de referência Laravel — drawer com rotas, models, eventos
function LaravelDrawer({ open, onToggle }) {
  return (
    <>
      <button className="lv-toggle" onClick={onToggle}>
        {open ? '✕ Fechar' : '⚡ Stack Laravel'}
      </button>
      <aside className={"laravel-drawer" + (open?" open":"")}>
        <div className="lv-h">
          <span className="badge">LARAVEL 11</span>
          <b>Stack de referência</b>
        </div>
        <div className="lv-body">
          <p>Esqueleto sugerido para portar este protótipo. Tempo real via <b>Laravel Reverb</b> (WebSocket nativo) + <b>Echo</b> no React.</p>

          <h3>Migrations</h3>
<pre>{`<span class="c">// database/migrations/...</span>
Schema::create(<span class="s">'companies'</span>, fn (<span class="v">$t</span>) =&gt; {
  <span class="v">$t</span>-&gt;id();
  <span class="v">$t</span>-&gt;<span class="k">string</span>(<span class="s">'name'</span>);
  <span class="v">$t</span>-&gt;<span class="k">string</span>(<span class="s">'slug'</span>)-&gt;unique();
});

Schema::create(<span class="s">'conversations'</span>, fn (<span class="v">$t</span>) =&gt; {
  <span class="v">$t</span>-&gt;id();
  <span class="v">$t</span>-&gt;foreignId(<span class="s">'company_id'</span>)-&gt;constrained();
  <span class="v">$t</span>-&gt;<span class="k">enum</span>(<span class="s">'kind'</span>, [<span class="s">'os'</span>,<span class="s">'team'</span>,<span class="s">'client'</span>]);
  <span class="v">$t</span>-&gt;foreignId(<span class="s">'order_id'</span>)-&gt;nullable();
  <span class="v">$t</span>-&gt;<span class="k">string</span>(<span class="s">'title'</span>);
  <span class="v">$t</span>-&gt;timestamps();
});

Schema::create(<span class="s">'messages'</span>, fn (<span class="v">$t</span>) =&gt; {
  <span class="v">$t</span>-&gt;id();
  <span class="v">$t</span>-&gt;foreignId(<span class="s">'conversation_id'</span>)-&gt;constrained();
  <span class="v">$t</span>-&gt;foreignId(<span class="s">'user_id'</span>)-&gt;constrained();
  <span class="v">$t</span>-&gt;text(<span class="s">'body'</span>)-&gt;nullable();
  <span class="v">$t</span>-&gt;json(<span class="s">'attachments'</span>)-&gt;nullable();
  <span class="v">$t</span>-&gt;<span class="k">boolean</span>(<span class="s">'is_note'</span>)-&gt;<span class="k">default</span>(<span class="n">false</span>);
  <span class="v">$t</span>-&gt;timestamp(<span class="s">'read_at'</span>)-&gt;nullable();
  <span class="v">$t</span>-&gt;timestamps();
});`}</pre>

          <h3>Routes (api.php)</h3>
<pre>{`Route::middleware(<span class="s">'auth:sanctum'</span>)-&gt;group(fn () =&gt; {
  Route::get   (<span class="s">'/conversations'</span>,             [ConvCtrl::<span class="k">class</span>, <span class="s">'index'</span>]);
  Route::get   (<span class="s">'/conversations/{id}/messages'</span>,[MsgCtrl::<span class="k">class</span>,  <span class="s">'index'</span>]);
  Route::post  (<span class="s">'/conversations/{id}/messages'</span>,[MsgCtrl::<span class="k">class</span>,  <span class="s">'store'</span>]);
  Route::post  (<span class="s">'/messages/{id}/read'</span>,         [MsgCtrl::<span class="k">class</span>,  <span class="s">'read'</span>]);
});`}</pre>

          <h3>Broadcast — MessageSent</h3>
<pre>{`<span class="k">class</span> <span class="n">MessageSent</span> <span class="k">implements</span> ShouldBroadcast {
  <span class="k">use</span> Dispatchable, InteractsWithSockets, SerializesModels;

  <span class="k">public function</span> __construct(<span class="k">public</span> Message <span class="v">$message</span>) {}

  <span class="k">public function</span> broadcastOn(): <span class="n">PrivateChannel</span> {
    <span class="k">return new</span> PrivateChannel(<span class="s">"chat.</span><span class="v">{</span><span class="v">$this</span>-&gt;message-&gt;conversation_id<span class="v">}</span><span class="s">"</span>);
  }
}`}</pre>

          <h3>routes/channels.php</h3>
<pre>{`Broadcast::channel(<span class="s">'chat.{convId}'</span>, fn (<span class="v">$user</span>, <span class="v">$convId</span>) =&gt;
  <span class="v">$user</span>-&gt;conversations()-&gt;whereKey(<span class="v">$convId</span>)-&gt;exists()
);`}</pre>

          <h3>Frontend (React + Echo)</h3>
<pre>{`<span class="k">import</span> Echo <span class="k">from</span> <span class="s">'laravel-echo'</span>;

useEffect(() =&gt; {
  <span class="k">const</span> ch = window.Echo.private(<span class="s">\`chat.</span><span class="v">${'$'}{convId}</span><span class="s">\`</span>);
  ch.listen(<span class="s">'MessageSent'</span>, (e) =&gt; setMsgs(prev =&gt; [...prev, e.message]));
  <span class="k">return</span> () =&gt; window.Echo.leave(<span class="s">\`chat.</span><span class="v">${'$'}{convId}</span><span class="s">\`</span>);
}, [convId]);`}</pre>

          <h3>Estrutura sugerida</h3>
<pre>{`app/
├─ Models/         Company, Conversation, Message, User
├─ Events/         MessageSent, UserTyping, MessageRead
├─ Http/Controllers/Api/  ConvCtrl, MsgCtrl
└─ Policies/       ConversationPolicy
config/
├─ broadcasting.php  → reverb
└─ sanctum.php
resources/js/
├─ components/Chat/  Sidebar, ConvList, Thread
└─ hooks/useEcho.js`}</pre>

          <p style={{marginTop:14, fontSize:11.5}}>
            <b>Token-eficiente:</b> os componentes React acima estão prontos pra ser portados quase sem alteração — basta trocar os mocks por chamadas <code style={{fontFamily:'var(--font-mono)'}}>fetch('/api/...')</code> e plugar o Echo.
          </p>
        </div>
      </aside>
    </>
  );
}

window.LaravelDrawer = LaravelDrawer;
