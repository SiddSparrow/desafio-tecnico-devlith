# Notas de Implementação

## Instruções de Uso

O painel oferece três pontos de entrada para exportação:

**1. Exportar Todos os Alunos**
Na tela **Alunos**, clique em **Exportar Todos** (botão no cabeçalho, ao lado de "Novo Aluno"). Um modal de confirmação será exibido. Ao confirmar, a exportação é processada em segundo plano.

**2. Exportar Alunos Selecionados**
Na tela **Alunos**, selecione os registros desejados usando os checkboxes e clique em **Exportar Selecionados** no menu de ações em massa. Funciona tanto com seleção manual quanto com "selecionar todos".

**3. Exportar Alunos de uma Escola**
Na tela **Escolas**, clique em **Exportar Alunos** na linha da escola desejada. Exporta todos os alunos com matrícula naquela escola.

**Recebendo o arquivo**
Quando a exportação for concluída, o sino de notificações no canto superior direito exibirá um alerta com um link para download. Clique no link para baixar o arquivo `.xlsx`.

---

## Decisões Técnicas

### Performance

O principal desafio é exportar dezenas de milhares de registros sem travar a requisição HTTP, estourar memória ou degradar o banco.

**Processamento assíncrono em chunks com Bus::batch()**
A exportação é despachada como um job (`ExportarAlunosJob`) que divide os dados em chunks de 1.000 registros. Cheguei a testar com valores mais altos como 5000 e também com valores mais baixos como 500, porém, 1000 me pareceu um bom valor para não precisar de muitos workers ao mesmo tempo para processamentos muito longos (como a base inteira). Cada chunk é processado por um `ProcessarChunkExportJob` independente, todos rodando em paralelo em um worker dedicado (`queue: export`). Um job final (`GerarExcelFinalJob`) aguarda a conclusão do batch e gera o Excel consolidado.

**Paginação por cursor em vez de OFFSET**
Para identificar as fronteiras de cada chunk sem carregar todos os IDs na memória PHP, utiliza-se uma subquery com `ROW_NUMBER() OVER (ORDER BY id)`. Isso retorna apenas os IDs de limite (ex: 999, 1999, 2999...) e usa o índice primário — custo `O(log n + chunk)` independente do volume total. Cheguei a testar offset, porém, isso gerou grandes problemas de performance e o cursor pareceu muito mais performático.

**Query Builder puro, sem Eloquent**
Todo o processamento de chunks usa `DB::table()` direto. Evita a hidratação de modelos Eloquent para centenas de milhares de registros, reduzindo uso de memória e tempo de CPU.

**Tabela temporária por batch**
Cada chunk escreve em `exportacao_alunos_temp` com um `batch_id`. O job final faz uma única leitura dessa tabela filtrando pelo `batch_id` e gera o arquivo Excel. Isso evita passar grandes arrays entre jobs serializados. Essa técnica de dados "pseudoconsolidados" permite facilitar o processamento.

**Excel gerado com ZipArchive + XMLWriter**
A geração do `.xlsx` usa apenas extensões nativas do PHP, sem Maatwebsite/Excel ou PhpSpreadsheet. O XMLWriter escreve o arquivo linha a linha em streaming, mantendo o consumo de memória constante independente do número de linhas. Foi testado com Maatwebsite, porém, removido por não ter apresentado uma boa performance no processo que estava sendo usado.

**Seleção sem carregar modelos no Filament**
A bulk action usa `$livewire->selectedTableRecords` (array de IDs crus) em vez do parâmetro `Collection $records`, evitando que o Filament hidrate todos os modelos Eloquent ao usar "selecionar todos".

### UX

**Notificação de conclusão com link direto**
Ao fim da exportação, `GerarExcelFinalJob` envia uma notificação de banco de dados com um link de download. O link contém o caminho do arquivo criptografado via `encrypt()`, sem expor o filesystem.

**Download seguro e com nome descritivo**
O controller descriptografa o token e serve o arquivo com `response()->download()`. O nome do arquivo inclui contexto — escola e timestamp — para que o usuário identifique o arquivo sem abri-lo.

**Modais de confirmação em todas as ações**
Cada ponto de entrada exibe um modal explicando que o processamento ocorre em segundo plano, evitando cliques acidentais e alinhando a expectativa do usuário.

**Arquitetura de Actions desacoplada**
A lógica de negócio reside em `app/Actions/` (classes PHP puras, injetáveis). As classes em `app/Filament/Tables/Actions/` e `app/Filament/Pages/Actions/` cuidam apenas da configuração da UI do Filament. Os Resources ficam limpos, sem lógica inline.
